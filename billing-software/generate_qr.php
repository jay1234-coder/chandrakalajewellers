<?php
require_once 'libs/phpqrcode/qrlib.php';

$upi_id = "boism-9427080359@boi";
$payee_name = "Chandrakala Jewellers";

$upi = "upi://pay?pa=" . rawurlencode($upi_id) . "&pn=" . rawurlencode($payee_name) . "&cu=INR";

/* 1. Generate QR Core Matrix safely via temp file with memory and GD fallbacks */
$qr = null;
$tmpQr = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cj_qr_' . md5($upi . microtime(true)) . '.png';

try {
    QRcode::png($upi, $tmpQr, QR_ECLEVEL_H, 10, 1);
    if (file_exists($tmpQr) && filesize($tmpQr) > 0) {
        $qr = @imagecreatefrompng($tmpQr);
        @unlink($tmpQr);
    }
} catch (Throwable $e) {
    if (file_exists($tmpQr)) @unlink($tmpQr);
}

// Fallback 1: in-memory stream if file writing is restricted
if (!$qr) {
    ob_start();
    QRcode::png($upi, false, QR_ECLEVEL_H, 10, 1);
    $qrData = ob_get_clean();
    if (!empty($qrData)) {
        $qr = @imagecreatefromstring($qrData);
    }
}

// Fallback 2: safe placeholder so it NEVER throws fatal error
if (!$qr) {
    $qr = imagecreatetruecolor(400, 400);
    $qBg = imagecolorallocate($qr, 255, 255, 255);
    $qFg = imagecolorallocate($qr, 150, 0, 24);
    imagefill($qr, 0, 0, $qBg);
    imagerectangle($qr, 10, 10, 390, 390, $qFg);
    imagestring($qr, 5, 80, 190, "BHIM UPI PAYMENT QR", $qFg);
}

/* 2. Add Premium Center Logo Mask */
$logoPath = "assets/logo.png";
if(file_exists($logoPath)){
    $logo = imagecreatefrompng($logoPath);

    $qrWidth  = imagesx($qr);
    $qrHeight = imagesy($qr);
    $logoWidth  = imagesx($logo);
    $logoHeight = imagesy($logo);

    $logoQRWidth = $qrWidth / 4.5; 
    $scale = $logoWidth / $logoQRWidth;
    $logoQRHeight = $logoHeight / $scale;

    $posX = ($qrWidth - $logoQRWidth) / 2;
    $posY = ($qrHeight - $logoQRHeight) / 2;

    $whiteColor = imagecolorallocate($qr, 255, 255, 255);
    imagefilledrectangle($qr, $posX - 6, $posY - 6, $posX + $logoQRWidth + 6, $posY + $logoQRHeight + 6, $whiteColor);

    imagecopyresampled($qr, $logo, $posX, $posY, 0, 0, $logoQRWidth, $logoQRHeight, $logoWidth, $logoHeight);
}

/* 3. Setup Widescreen Landscape Screen Dimensions (16:9 Fullscreen) */
$width  = 1920;
$height = 1080;
$card = imagecreatetruecolor($width, $height);

/* 4. Luxury Color Archetype Palette (Ink-Saver Optimized) */
$bgWhite     = imagecolorallocate($card, 255, 255, 255);
$brandRed    = imagecolorallocate($card, 150, 0, 24);     
$luxuryGold  = imagecolorallocate($card, 212, 175, 55);   
$textDark    = imagecolorallocate($card, 26, 26, 26);     
$panelGrey   = imagecolorallocate($card, 248, 249, 250);  

// Fill the entire canvas with white to save printer ink
imagefill($card, 0, 0, $bgWhite);

/* 5. Cross-Platform Font Detection (Windows & Linux / Vercel Serverless) */
$fontCandidates = [
    'C:\Windows\Fonts\timesbd.ttf',
    'C:\Windows\Fonts\arialbd.ttf',
    'C:\Windows\Fonts\times.ttf',
    '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
    '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
    '/usr/share/fonts/truetype/freefont/FreeSansBold.ttf',
    dirname(__DIR__) . '/public/fonts/Outfit-Bold.ttf'
];

$fontBold = '';
foreach ($fontCandidates as $fc) {
    if (file_exists($fc)) {
        $fontBold = $fc;
        break;
    }
}
$fontRegular = $fontBold;

if (!function_exists('cj_draw_qr_text')) {
    function cj_draw_qr_text($img, $size, $angle, $x, $y, $col, $font, $text) {
        if (!empty($font) && file_exists($font)) {
            @imagettftext($img, $size, $angle, (int)$x, (int)$y, $col, $font, $text);
        } else {
            $strSize = min(5, max(2, (int)($size / 7)));
            @imagestring($img, $strSize, (int)$x, (int)($y - $size * 0.8), $text, $col);
        }
    }
} 

/* 6. Center-Right Main Container Card (Centered Placement Framework) */
$boxLeft   = 560;
$boxRight  = 1360;
$boxTop    = 60;
$boxBottom = 1000;

imagefilledrectangle($card, $boxLeft, $boxTop, $boxRight, $boxBottom, $panelGrey);
imagerectangle($card, $boxLeft, $boxTop, $boxRight, $boxBottom, $luxuryGold);

/* 7. Position and Render the Main QR Code */
$qrSize = 520;
$qrX    = 700; 
$qrY    = 110;

imagerectangle($card, $qrX - 10, $qrY - 10, $qrX + $qrSize + 10, $qrY + $qrSize + 10, $luxuryGold);
imagecopyresampled($card, $qr, $qrX, $qrY, 0, 0, $qrSize, $qrSize, imagesx($qr), imagesy($qr));

/* 8. Relocated Text Stack: Positions branding details beautifully BELOW the QR Frame */
// "SCAN TO PAY" Title String
cj_draw_qr_text($card, 38, 0, 805, 700, $brandRed, $fontBold, "SCAN TO PAY");
imagefilledrectangle($card, 805, 720, 1115, 724, $luxuryGold);

// Company Name Identity Elements
cj_draw_qr_text($card, 32, 0, 690, 790, $textDark, $fontBold, "CHANDRAKALA JEWELLERS");

/* 9. Render Interactive Bottom VPA Information Field Card */
$vpaY = 840;
imagefilledrectangle($card, 710, $vpaY, 1210, $vpaY + 60, $brandRed);
imagefilledrectangle($card, 705, $vpaY, 710, $vpaY + 60, $luxuryGold); 

$vpaText = "UPI ID: " . $upi_id;
cj_draw_qr_text($card, 18, 0, 740, $vpaY + 38, $bgWhite, $fontBold, $vpaText);

/* 10. Process Integrated Payment Ecosystem Badges */
$badgeY = 925;
if(file_exists('assets/upi_logo.png')){
    $upiLogo = imagecreatefrompng('assets/upi_logo.png');
    imagecopyresampled($card, $upiLogo, 760, $badgeY, 0, 0, 130, 43, imagesx($upiLogo), imagesy($upiLogo));
}
if(file_exists('assets/bhim_logo.png')){
    $bhimLogo = imagecreatefrompng('assets/bhim_logo.png');
    imagecopyresampled($card, $bhimLogo, 1020, $badgeY, 0, 0, 105, 48, imagesx($bhimLogo), imagesy($bhimLogo));
}

// 11. Final output execution
header("Content-Type: image/png");
imagepng($card);

/* 12. Housekeeping */
imagedestroy($qr);
imagedestroy($card);
if(isset($logo)) imagedestroy($logo);
if(isset($upiLogo)) imagedestroy($upiLogo);
if(isset($bhimLogo)) imagedestroy($bhimLogo);
?>