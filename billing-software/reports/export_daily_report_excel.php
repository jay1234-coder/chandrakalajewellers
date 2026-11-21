<?php
require '../vendor/autoload.php';
include '../includes/db.php';
date_default_timezone_set('Asia/Kolkata');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

$filter_type   = $_GET['filter_type'] ?? 'daily';
$selected_date = $_GET['report_date'] ?? date('Y-m-d');
$start_date    = $_GET['start_date'] ?? date('Y-m-d');
$end_date      = $_GET['end_date'] ?? date('Y-m-d');
$target_month  = $_GET['target_month'] ?? date('Y-m');
$target_year   = $_GET['target_year'] ?? date('Y');

function validate_date($d, $f = 'Y-m-d') { 
    $x = DateTime::createFromFormat($f, $d); 
    return $x && $x->format($f) === $d; 
}
function validate_month($d) { return validate_date($d . '-01'); }

if (!validate_date($selected_date)) $selected_date = date('Y-m-d');
if (!validate_date($start_date))    $start_date = date('Y-m-d');
if (!validate_date($end_date))      $end_date = date('Y-m-d');
if (!validate_month($target_month))  $target_month = date('Y-m');
if (!preg_match('/^\d{4}$/', $target_year)) $target_year = date('Y');

// --- DYNAMIC FILENAME & HEADER DATE RANGE LOGIC ---
$print_date_label = '';
$filename = 'CJ_Report';

if ($filter_type === 'custom') {
    $p_start = $start_date; 
    $p_end = $end_date;
    $print_date_label = date('d/m/Y', strtotime($start_date)) . " to " . date('d/m/Y', strtotime($end_date));
    
    // Formatting: CJ_1-juneto25-june
    $s_part = strtolower(date('j-M', strtotime($start_date)));
    $e_part = strtolower(date('j-M', strtotime($end_date)));
    $filename = "CJ_" . $s_part . "to" . $e_part;

} elseif ($filter_type === 'monthly') {
    $p_start = $target_month . '-01';
    $p_end = date('Y-m-t', strtotime($p_start));
    $print_date_label = date('01/m/Y', strtotime($p_start)) . " to " . date('t/m/Y', strtotime($p_start));
    
    // Formatting: CJ_Monthly_1-juneto25june
    $m_start = strtolower(date('j-M', strtotime($p_start)));
    $m_end = strtolower(date('jtM', strtotime($p_end))); // Day number + short month directly joined
    $filename = "CJ_Monthly_" . $m_start . "to" . $m_end;

} elseif ($filter_type === 'yearly') {
    $next = $target_year + 1;
    $p_start = "$target_year-04-01";
    $p_end = "$next-03-31";
    $print_date_label = "01/04/{$target_year} to 31/03/{$next}";
    
    // Formatting: CJ_FY-25toFY-26
    $y_short = substr($target_year, 2);
    $n_short = substr($next, 2);
    $filename = "CJ_FY-" . $y_short . "toFY-" . $n_short;

} else {
    $p_start = $selected_date;
    $p_end = $selected_date;
    $print_date_label = date('d/m/Y', strtotime($selected_date));
    
    // Formatting: CJ_Daily_june25
    $date_part = strtolower(date('Fj', strtotime($selected_date)));
    $filename = "CJ_Daily_" . $date_part;
}

// Strip out any accidental spaces or syntax issues in the filename
$filename = str_replace(' ', '', $filename);

$sql_cond = "WHERE b.bill_date BETWEEN ? AND ? AND COALESCE(UPPER(b.status),'') <> 'CANCELLED'";

$query = "SELECT
            b.id, b.bill_no, b.bill_date, b.customer_name,
            COALESCE(b.gst_no,'') as gst_no,
            b.net_amount, b.cgst, b.sgst,
            GROUP_CONCAT(DISTINCT bi.hsn_code SEPARATOR ', ') as dynamic_hsn
          FROM bills b
          LEFT JOIN bill_items bi ON b.id = bi.bill_id
          $sql_cond
          GROUP BY b.id
          ORDER BY b.bill_date ASC, b.id ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $p_start, $p_end);
$stmt->execute();
$res = $stmt->get_result();

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Daily Report');

$sheet->setShowGridlines(true);

$sheet->setCellValue('A1', 'LEDGER: CHANDRAKALA JEWELLERS');
$sheet->setCellValue('F1', 'Date Range: ' . $print_date_label);

$sheet->mergeCells('A1:E1');
$sheet->mergeCells('F1:I1');

$sheet->getStyle('A1')->getFont()->setName('Calibri')->setSize(14)->setBold(true);
$sheet->getStyle('F1')->getFont()->setName('Calibri')->setSize(11)->setBold(true);
$sheet->getStyle('F1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

$headers = ['DATE', 'PARTICULARS', 'VCH NO.', 'GST NO.', 'HSNCODE', 'GROSS TOTAL', 'CGST@1.50%', 'SGST@1.50%', 'SALES'];
$sheet->fromArray($headers, NULL, 'A3');

$r = 4;
$tg = $tc = $ts = $sale = 0;

while ($row = $res->fetch_assoc()) {
    $gross = (float)$row['net_amount'];
    $cgst  = (float)$row['cgst'];
    $sgst  = (float)$row['sgst'];
    $sales = $gross - ($cgst + $sgst);

    $tg   += $gross;
    $tc   += $cgst;
    $ts   += $sgst;
    $sale += $sales;

    $sheet->setCellValue("A$r", date('d/m/Y', strtotime($row['bill_date'])));
    $sheet->setCellValue("B$r", $row['customer_name'] ?: 'Retail Sale');
    $sheet->setCellValue("C$r", $row['bill_no']);
    $sheet->setCellValue("D$r", $row['gst_no'] ?: 'n/a');
    $sheet->setCellValue("E$r", $row['dynamic_hsn'] ?: '7113');
    $sheet->setCellValue("F$r", $gross);
    $sheet->setCellValue("G$r", $cgst);
    $sheet->setCellValue("H$r", $sgst);
    $sheet->setCellValue("I$r", $sales);
    $r++;
}

$sheet->setCellValue("A$r", "Summary Grand Total:");
$sheet->mergeCells("A$r:E$r");

$sheet->setCellValue("F$r", $tg);
$sheet->setCellValue("G$r", $tc);
$sheet->setCellValue("H$r", $ts);
$sheet->setCellValue("I$r", $sale);

$row_gen_by = $r + 3;
$sheet->setCellValue("A$row_gen_by", "Generated By: System");
$sheet->setCellValue("G$row_gen_by", "Generated On: " . date('d/m/Y h:i A'));
$sheet->mergeCells("G$row_gen_by:I$row_gen_by");

$sheet->getStyle("A$row_gen_by")->getFont()->setItalic(true)->setSize(10);
$sheet->getStyle("G$row_gen_by")->getFont()->setItalic(true)->setSize(10);
$sheet->getStyle("G$row_gen_by")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

$sheet->getStyle("A3:I$r")->getFont()->setName('Calibri')->setSize(11);
$sheet->getRowDimension(1)->setRowHeight(28);
$sheet->getRowDimension(3)->setRowHeight(26);

$headerStyle = [
    'font' => ['bold' => true],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'FFFFFF']
    ]
];
$sheet->getStyle('A3:I3')->applyFromArray($headerStyle);

$sheet->getStyle("A4:A$r")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle("B4:B$r")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
$sheet->getStyle("C4:E$r")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->getStyle("B4:B" . ($r - 1))->getFont()->setBold(true);

$currencyFormat = '[$₹-409]#,##0.00;([$₹-409]#,##0.00);"-"';
$sheet->getStyle("F4:I$r")->getNumberFormat()->setFormatCode($currencyFormat);
$sheet->getStyle("F4:I$r")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

$sheet->getStyle("A$r:I$r")->getFont()->setBold(true);
$sheet->getStyle("A$r")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

$thinBorder = ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']];
$mediumBorder = ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '000000']];
$doubleBorder = ['borderStyle' => Border::BORDER_DOUBLE, 'color' => ['rgb' => '000000']];

$sheet->getStyle("A3:I$r")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

$sheet->getStyle('A3:I3')->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM);
$sheet->getStyle('A3:I3')->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM);

$sheet->getStyle("A$r:I$r")->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
$sheet->getStyle("A$r:I$r")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_DOUBLE);

$sheet->getStyle("A3:A$r")->getBorders()->getLeft()->setBorderStyle(Border::BORDER_MEDIUM);
$sheet->getStyle("I3:I$r")->getBorders()->getRight()->setBorderStyle(Border::BORDER_MEDIUM);
$sheet->getStyle("A$r:I$r")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM);

foreach (range('A', 'I') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

$sheet->freezePane('A4');

$writer = new Xlsx($spreadsheet);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
// --- Dynamic Name Passed to Content-Disposition Header ---
header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
header('Cache-Control: max-age=0');

$writer->save('php://output');
exit;