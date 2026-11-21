<?php
require_once 'libs/phpqrcode/qrlib.php';

$upi_id = "boism-9427080359@boi";
$payee_name = "Chandrakala Jewellers";

$upi = "upi://pay?pa=" . rawurlencode($upi_id) . "&pn=" . rawurlencode($payee_name) . "&cu=INR";

/* 1. Generate QR Core Matrix directly in memory (No temp file) */
ob_start();
QRcode::png($upi, null, QR_ECLEVEL_H, 10, 1);
$qrData = ob_get_clean();
$qr = imagecreatefromstring($qrData);

if (!$qr) {
    die("Failed to generate QR Code Matrix.");
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

/* 5. Path to Windows Standard Times New Roman Fonts */
$fontRegular = 'C:\Windows\Fonts\times.ttf';
$fontBold    = 'C:\Windows\Fonts\timesbd.ttf';

if (!function_exists('cj_draw_qr_text')) {
    function cj_draw_qr_text($img, $size, $angle, $x, $y, $col, $font, $text) {
        if (!empty($font) && file_exists($font)) {
            @imagettftext($img, $size, $angle, $x, $y, $col, $font, $text);
        } else {
            @imagestring($img, 5, (int)$x, (int)($y - $size), $text, $col);
        }
    }
}

if (!file_exists($fontBold)) { 
    $fontBold = file_exists($fontRegular) ? $fontRegular : ''; 
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