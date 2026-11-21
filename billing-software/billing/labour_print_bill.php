<?php
include '../includes/db.php';

if (!isset($_GET['id']) || empty($_GET['id']) || intval($_GET['id']) <= 0) {
    die("Error: Invoice ID is missing or invalid.");
}

$bill_id = intval($_GET['id']);
$bill_res = mysqli_query($conn, "SELECT * FROM bills WHERE id = $bill_id AND bill_type = 'LABOUR'");
if (mysqli_num_rows($bill_res) == 0) { 
    die("Error: Labour Invoice not found."); 
}
$bill = mysqli_fetch_assoc($bill_res);
$items_res = mysqli_query($conn, "SELECT * FROM bill_items WHERE bill_id = $bill_id");

// Catch print size parameter. Default to A4 if not provided.
$print_size = isset($_GET['size']) && strtolower($_GET['size']) === 'a5' ? 'a5' : 'a4';

function getAmountInWords($number) {
    $decimal = round($number - ($no = floor($number)), 2) * 100;
    $i = 0; $str = array();
    $words = array(
        0 => '', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five', 6 => 'Six',
        7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve',
        13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen', 
        18 => 'Eighteen', 19 => 'Nineteen', 20 => 'Twenty', 30 => 'Thirty', 40 => 'Forty', 
        50 => 'Fifty', 60 => 'Sixty', 70 => 'Seventy', 80 => 'Eighty', 90 => 'Ninety'
    );
    $digits = array('', 'Hundred ','Thousand ','Lakh ', 'Crore ');
    
    while ($no > 0) {
        $divider = ($i == 2) ? 10 : 100;
        $current_chunk = floor($no % $divider); 
        $no = floor($no / $divider); 
        
        if ($current_chunk) {
            $counter = $i == 0 ? 0 : ($i == 1 ? 1 : floor($i / 2) + 1);
            $digit_label = isset($digits[$counter]) ? $digits[$counter] : '';
            
            if ($current_chunk < 21) {
                $str[] = ($words[$current_chunk] ?? '') . ' ' . $digit_label;
            } else {
                $str[] = ($words[floor($current_chunk / 10) * 10] ?? '') . ' ' . ($words[$current_chunk % 10] ?? '') . ' ' . $digit_label;
            }
        }
        $i += $divider == 10 ? 1 : 2;
    }
    
    $Rupees = implode('', array_reverse($str));
    $paise = ($decimal > 0) ? " and " . (($words[floor($decimal / 10) * 10] ?? '') . " " . ($words[$decimal % 10] ?? '')) . ' Paise' : '';
    
    $final_words = trim(preg_replace('/\s+/', ' ', $Rupees));
    return "Rupees " . ($final_words ? $final_words : 'Zero') . $paise . " only";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Labour_<?= htmlspecialchars($bill['customer_name']); ?>_<?= date('d-m-Y', strtotime($bill['bill_date'])); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        /* Conditional Setup dynamically handling sizing metrics */
        <?php if ($print_size === 'a5'): ?>
            @page { size: A5 portrait; margin: 4mm; }
            body { font-size: 10px; line-height: 1.2; }
            .invoice-outer-box { min-height: 198mm; }
            .shop-header-title-section { font-size: 18px; padding: 4px 0; }
            .shop-meta-details { font-size: 8px; padding: 3px 0; }
            .items-table-grid th { padding: 4px 3px; font-size: 9.5px; }
            .items-table-grid td { padding: 4px 4px; font-size: 9.5px; }
            .print-metal-ledger { padding: 6px; font-size: 9.5px; width: 55%; }
            .summary-calculation-table { width: 45%; font-size: 9.5px; }
            .summary-calculation-table td { padding: 3px 6px; }
            .meta-left-panel, .meta-right-panel { padding: 4px 6px; }
            .meta-label { width: 75px; }
            .payment-breakdown-pane, .bank-details-pane, .legal-declaration-pane { padding: 4px 6px; }
            .qr-img { width: 48px; height: 48px; object-fit: contain; }
            .signature-row-container { padding: 10px 10px 4px 10px; }
        <?php else: ?>
            @page { size: A4 portrait; margin: 8mm; }
            body { font-size: 11px; line-height: 1.3; }
            .invoice-outer-box { min-height: 275mm; }
            .shop-header-title-section { font-size: 24px; padding: 8px 0; }
            .shop-meta-details { font-size: 10px; padding: 5px 0; }
            .items-table-grid th { padding: 6px 5px; font-size: 11px; }
            .items-table-grid td { padding: 8px 6px; font-size: 11px; }
            .print-metal-ledger { padding: 10px; font-size: 11px; width: 60%; }
            .summary-calculation-table { width: 35%; font-size: 11px; }
            .summary-calculation-table td { padding: 5px 8px; }
            .meta-left-panel, .meta-right-panel { padding: 6px 8px; }
            .meta-label { width: 100px; }
            .payment-breakdown-pane, .bank-details-pane, .legal-declaration-pane { padding: 6px 8px; }
            .qr-img { width: 65px; height: 65px; object-fit: contain; }
            .signature-row-container { padding: 15px 10px 4px 10px; }
        <?php endif; ?>

        body { font-family: 'Arial', sans-serif; background: #fff; margin: 0; padding: 0; color: #000; }
        
        .invoice-outer-box {
            border: 2px solid #000;
            max-width: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-sizing: border-box;
            page-break-inside: avoid;
        }

        .shop-header-title-section {
            background-color: #dc2626 !important;
            color: #ffffff !important;
            text-align: center;
            text-transform: uppercase;
            font-weight: bold;
            letter-spacing: 1px;
            font-family: 'Georgia', serif;
            border-bottom: 2px solid #000;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .shop-meta-details {
            text-align: center;
            font-weight: bold;
            line-height: 1.3;
            border-bottom: 1px solid #000;
            text-transform: uppercase;
        }

        .customer-meta-grid {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            border-bottom: 2px solid #000;
        }

        .meta-left-panel { border-right: 2px solid #000; }
        .meta-right-panel { text-align: right; }
        .meta-data-row { display: flex; margin-bottom: 4px; }
        .meta-label { font-weight: bold; text-transform: uppercase; }
        .meta-value { flex: 1; }

        .document-type-banner {
            text-align: center;
            font-weight: bold;
            font-size: 12px;
            padding: 6px 0;
            border-bottom: 2px solid #000;
            text-transform: uppercase;
            background: #ffffff;
            color: #000000;
            letter-spacing: 1px;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .items-table-grid { width: 100%; border-collapse: collapse; margin-bottom: auto; }
        .items-table-grid th {
            border-bottom: 2px solid #000;
            border-right: 2px solid #000;
            background: #f8fafc;
            font-weight: bold;
            text-transform: uppercase;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        .items-table-grid th:last-child { border-right: none; }
        .items-table-grid td { border-bottom: 2px solid #000; border-right: 2px solid #000; vertical-align: middle; }
        .items-table-grid td:last-child { border-right: none; }

        .financial-summary-block { display: flex; justify-content: space-between; border-top: 2px solid #000; }
        .summary-calculation-table { border-collapse: collapse; border-left: 2px solid #000; }
        .summary-calculation-table td { border-bottom: 1px solid #000; }
        .summary-calculation-table tr:last-child td { border-bottom: none; }
        .summary-calculation-table td:last-child { text-align: right; font-weight: bold; }

        .grand-total-highlight-row {
            background-color: #dc2626 !important;
            color: #ffffff !important;
            font-weight: bold;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .bottom-terms-and-signatures { border-top: 2px solid #000; }
        .chargeable-words-bar { padding: 6px 8px; border-bottom: 2px solid #000; }
        .payment-and-bank-grid { display: grid; grid-template-columns: 1.1fr 0.9fr; border-bottom: 2px solid #000; }
        .payment-breakdown-pane { border-right: 2px solid #000; }
        
        .bank-info-and-qr-flex {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
            margin-top: 4px;
        }
        .qr-codes-container { display: flex; gap: 8px; }
        .qr-wrapper { text-align: center; }
        .qr-img { border: 1px solid #ccc; display: block; }
        .qr-label {
            font-size: 8px;
            font-weight: bold;
            margin-top: 2px;
            text-transform: uppercase;
        }

        .compact-flex-row { display: flex; justify-content: space-between; margin-bottom: 2px; max-width: 250px; }
        .legal-declaration-pane { font-size: 10px; line-height: 1.3; }
        .signature-row-container { display: flex; justify-content: space-between; align-items: flex-end; }

        @media print {
            body { margin: 0; padding: 0; background: #fff; }
            .no-print { display: none !important; }
            .invoice-outer-box { max-height: none; min-height: <?= ($print_size === 'a5') ? '198mm' : '275mm'; ?>; }
            .shop-header-title-section { background-color: #dc2626 !important; color: #ffffff !important; }
        }
    </style>
</head>
<body>

<div class="no-print" style="background:#f1f5f9; padding:12px; text-align:right; border-bottom:1px solid #cbd5e1; display:flex; justify-content:space-between; align-items:center; gap:10px;">
    <div style="display: flex; gap: 5px;">
        <a href="?id=<?= $bill_id; ?>&size=a4" style="text-decoration:none; padding:6px 14px; background:<?= $print_size === 'a4' ? '#dc2626' : '#cbd5e1'; ?>; color:<?= $print_size === 'a4' ? '#fff' : '#334155'; ?>; font-weight:bold; border-radius:4px; font-size:12px;">A4 Format</a>
        <a href="?id=<?= $bill_id; ?>&size=a5" style="text-decoration:none; padding:6px 14px; background:<?= $print_size === 'a5' ? '#dc2626' : '#cbd5e1'; ?>; color:<?= $print_size === 'a5' ? '#fff' : '#334155'; ?>; font-weight:bold; border-radius:4px; font-size:12px;">A5 Format</a>
    </div>

    <div style="display:flex; gap:10px;">
        <button onclick="window.print();" style="padding:8px 20px; background:#ef4444; color:#fff; border:none; border-radius:6px; cursor:pointer; font-weight:bold;"><i class="fas fa-print"></i> Print Invoice</button>
        <button onclick="window.location.href='bill_history.php';" style="padding:8px 20px; background:#4b5563; color:#fff; border:none; border-radius:6px; cursor:pointer; font-weight:600;">Back to History</button>
    </div>
</div>

<div class="invoice-outer-box">
    <div>
        <div class="shop-header-title-section">Chandrakala Jewellers</div>
        
        <div class="shop-meta-details">
            MFR Dealers Gold Silver Ornaments, Bullion<br>
            Civil Road Khedbrahma Dist S K 383255<br>
            Email : chandrakalajewellers849@gmail.com | Phone : 9427080359 <br>
            GST NO 24ALSPS5998L1ZU PAN ALSPS5998L | State Gujarat, Code 24
        </div>

        <div class="customer-meta-grid">
            <div class="meta-left-panel">
                <div class="meta-data-row"><div class="meta-label">Bill No</div><div class="meta-value">: &nbsp;<strong><?= htmlspecialchars($bill['bill_no'] ?? ''); ?></strong></div></div>
                <div class="meta-data-row"><div class="meta-label">Buyers Name</div><div class="meta-value">: &nbsp;<strong><?= htmlspecialchars($bill['customer_name'] ?? ''); ?></strong></div></div>
                <div class="meta-data-row"><div class="meta-label">Address</div><div class="meta-value">: &nbsp;<?= !empty($bill['address']) ? htmlspecialchars($bill['address']) : ''; ?></div></div>
                <div class="meta-data-row"><div class="meta-label">GST No</div><div class="meta-value">: &nbsp;<?= !empty($bill['gst_no']) ? htmlspecialchars($bill['gst_no']) : ''; ?></div></div>
                <div class="meta-data-row"><div class="meta-label">State Code</div><div class="meta-value">: &nbsp;<?= !empty($bill['state_code']) ? htmlspecialchars($bill['state_code']) : '24'; ?></div></div>
                <div class="meta-data-row"><div class="meta-label">PAN No</div><div class="meta-value">: &nbsp;<?= !empty($bill['pan_no']) ? htmlspecialchars($bill['pan_no']) : ''; ?></div></div>
            </div>
            <div class="meta-right-panel">
                <div><strong>DATE:</strong> &nbsp;<?= !empty($bill['bill_date']) ? date('d/m/Y', strtotime($bill['bill_date'])) : ''; ?></div>
                <div style="margin-top: 12px;"><strong>Phone no:</strong> &nbsp;<?= htmlspecialchars($bill['mobile'] ?? ''); ?></div>
                <div style="margin-top: 4px;"><strong>Gold Rate:</strong> &nbsp;<?= number_format($bill['gold_rate'] ?? 0, 2); ?> /gm</div>
                <div style="margin-top: 4px;"><strong>Silver Rate:</strong> &nbsp;<?= isset($bill['silver_rate']) && floatval($bill['silver_rate']) > 0 ? number_format($bill['silver_rate'], 2) : '100.00'; ?> /gm</div>
            </div>
        </div>
        
        <div class="document-type-banner">Labour / Job Work Service Invoice</div>

        <table class="items-table-grid">
            <thead>
                <tr>
                    <th style="width: 5%; text-align:center;">Sr.</th>
                    <th style="width: 40%; text-align:left;">Description of Job Work / Services</th>
                    <th style="width: 15%; text-align:left;">Metal Category</th>
                    <th style="width: 12%; text-align:center;">SAC CODE</th>
                    <th style="width: 13%; text-align:right;">Weight / Qty (Gms)</th>
                    <th style="width: 15%; text-align:center;">Making %</th>
                    <th style="width: 15%; text-align:right;">Service Charge (₹)</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $sr = 1;
                $total_gold_weight = 0;
                while($item = mysqli_fetch_assoc($items_res)) {
                    $metal_type = !empty($item['item_metal_type']) ? trim(strtolower($item['item_metal_type'])) : trim(strtolower($item['making_amount']));
                    $qty = floatval($item['quantity'] ?? 0);
                    
                    if($metal_type === 'gold' || $metal_type === '') {
                        $total_gold_weight += $qty;
                    }
                    
                    echo "<tr>";
                    echo "<td style='text-align:center;'>".$sr++."</td>";
                    echo "<td>".htmlspecialchars($item['description'] ?? '')."</td>";
                    echo "<td style='text-transform: capitalize;'>".htmlspecialchars($metal_type)."</td>";
                    echo "<td style='text-align:center;'>".htmlspecialchars($item['hsn_code'] ?? '9988')."</td>";
                    echo "<td style='text-align:right;'>".number_format($qty, 3)."</td>";
                    echo "<td style='text-align:center;'>".number_format($item['making_percent'] ?? 0, 2)."%</td>";
                    echo "<td style='text-align:right;'>".number_format($item['total_amount'] ?? 0, 2)."</td>";
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <div class="bottom-terms-and-signatures">
        <div class="financial-summary-block">
            <div class="print-metal-ledger">
                <strong style="text-transform:uppercase; font-size:10px; display:block; margin-bottom:5px; border-bottom:1px solid #000; padding-bottom:2px;">Pure Metal Account Balances</strong>
                <table style="width:100%; font-size:11px; line-height:1.4;">
                    <tr><td>Customer Metal Deposit Credit:</td><td style="text-align:right; font-weight:bold;"><?= number_format($bill['gold_credit'] ?? 0, 3); ?> g</td></tr>
                    <tr><td>Total Built Ornaments Weight:</td><td style="text-align:right; font-weight:bold;"><?= number_format($total_gold_weight, 3); ?> g</td></tr>
                    <?php 
                    $diff = $total_gold_weight - floatval($bill['gold_credit'] ?? 0);
                    if ($diff < 0): ?>
                        <tr style="color:#16a34a; font-weight:bold;"><td>Gold Balance Return Weight:</td><td style="text-align:right;"><?= number_format(abs($diff), 3); ?> g</td></tr>
                    <?php elseif ($diff > 0): ?>
                        <tr style="color:#dc2626; font-weight:bold;"><td>Gold Over-Billed Metal Weight:</td><td style="text-align:right;"><?= number_format($diff, 3); ?> g</td></tr>
                    <?php endif; ?>
                </table>
            </div>

            <table class="summary-calculation-table">
                <tr><td>LABOUR TOTAL</td><td><?php echo number_format($bill['subtotal'] ?? 0, 2); ?></td></tr>
                <tr><td>DISCOUNT</td><td><?php echo number_format($bill['discount'] ?? 0, 2); ?></td></tr>
                <tr><td>TAXABLE VALUE</td><td><?php echo number_format((($bill['subtotal'] ?? 0) - ($bill['discount'] ?? 0)), 2); ?></td></tr>
                <tr><td>CGST (2.5%)</td><td><?php echo number_format($bill['cgst'] ?? 0, 2); ?></td></tr>
                <tr><td>SGST (2.5%)</td><td><?php echo number_format($bill['sgst'] ?? 0, 2); ?></td></tr>
                <tr class="grand-total-highlight-row"><td>GRAND TOTAL</td><td><?php echo number_format($bill['net_amount'] ?? 0, 2); ?></td></tr>
            </table>
        </div>
        <div style="text-align:right; font-size:9px; font-weight:bold; padding-right:8px; margin: 2px 0;">E&OE</div>

        <div class="chargeable-words-bar">
            <strong>Amount chargeable in words:</strong> <span style="text-transform: capitalize;"><?php echo getAmountInWords($bill['net_amount'] ?? 0); ?></span>
        </div>

        <div class="payment-and-bank-grid">
            <div class="payment-breakdown-pane">
                <strong>Remittance Tracking Summary:</strong><br>
                <div class="compact-flex-row" style="margin-top:6px;"><span>Cash Remitted</span>#₹<?php echo number_format($bill['cash_received'] ?? 0, 2); ?>#</div>
                <div class="compact-flex-row"><span>Bank / UPI Remitted</span>#₹<?php echo number_format($bill['bank_received'] ?? 0, 2); ?>#</div>
                <div class="compact-flex-row" style="border-top:1px dashed #777; margin-top:2px; padding-top:2px;"><span>Total Settled</span>#₹<?php echo number_format((($bill['cash_received'] ?? 0) + ($bill['bank_received'] ?? 0)), 2); ?>#</div>
                <div class="compact-flex-row" style="color:#dc2626;"><span>Balance Outstanding</span>#₹<?php echo number_format($bill['balance_amount'] ?? 0, 2); ?>#</div>
            </div>
            <div class="bank-details-pane">
                <strong>Settlement Bank Routing Details</strong><br>
                <div class="bank-info-and-qr-flex">
                    <table style="font-size:11px; line-height:1.3; flex: 1;">
                        <tr><td style="width:65px; color:#555;">Bank name</td><td>: Bank of India, Khedbrahma</td></tr>
                        <tr><td style="color:#555;">A/c no</td><td>: <strong>240420110000135</strong></td></tr>
                        <tr><td style="color:#555;">Ifsc</td><td>: <strong>BKID0002404</strong></td></tr>
                    </table>
                    <?php
                    // Resolve QR 1 code (prioritizes base64 data URI for instant, 100% reliable printing)
                    $qr1_candidates = [
                        __DIR__ . '/../uploads/qr/qr1.jpeg',
                        dirname(__DIR__, 2) . '/billing-software/uploads/qr/qr1.jpeg',
                        dirname(__DIR__, 2) . '/public/billing-software/uploads/qr/qr1.jpeg',
                    ];
                    $qr1_src = '/billing-software/uploads/qr/qr1.jpeg';
                    foreach ($qr1_candidates as $cand) {
                        if (file_exists($cand) && filesize($cand) > 0) {
                            $qr1_src = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($cand));
                            break;
                        }
                    }

                    // Resolve QR 2 code
                    $qr2_candidates = [
                        __DIR__ . '/../uploads/qr/qr2.jpg',
                        dirname(__DIR__, 2) . '/billing-software/uploads/qr/qr2.jpg',
                        dirname(__DIR__, 2) . '/public/billing-software/uploads/qr/qr2.jpg',
                    ];
                    $qr2_src = '/billing-software/uploads/qr/qr2.jpg';
                    foreach ($qr2_candidates as $cand) {
                        if (file_exists($cand) && filesize($cand) > 0) {
                            $qr2_src = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($cand));
                            break;
                        }
                    }
                    ?>
                    <div class="qr-codes-container">
                        <div class="qr-wrapper">
                            <img src="<?= $qr1_src ?>" alt="QR 1" class="qr-img">
                            <div class="qr-label">QR 1</div>
                        </div>
                        <div class="qr-wrapper">
                            <img src="<?= $qr2_src ?>" alt="QR 2" class="qr-img">
                            <div class="qr-label">QR 2</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="legal-declaration-pane">
            <strong>Declaration:</strong> We declare that this invoice shows the actual price of the services described and that all particulars are true and correct.
        </div>

        <div class="signature-row-container">
            <div style="font-weight:bold;">Customer Signature</div>
            <div style="text-align:right;">
                <div style="font-size:11px; font-weight:bold; margin-bottom:25px;">For, CHANDRAKALA JEWELLERS</div>
                <div style="border-top:1px solid #666; width:180px; text-align:center; font-size:10px; padding-top:2px;">Auth Sign/Propreitor</div>
            </div>
        </div>
    </div>
</div>
</body>
</html>