<?php
/**
 * ChandraKala Jewellers — Google Merchant Center Automated XML Product Feed.
 * Conforms to Google Merchant Center RSS 2.0 / Google Shopping Feed specifications.
 * Allows organic product listing in the Google Shopping tab across Gujarat and India.
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/img-helper.php';
require_once __DIR__ . '/includes/pricing.php';
require_once __DIR__ . '/includes/seo.php';

header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age=3600');

$standard_rates = isset($conn) && $conn instanceof mysqli ? cj_get_standard_rates($conn) : [];
$manual_rates = isset($conn) && $conn instanceof mysqli ? cj_get_manual_rates($conn) : [];

if (!function_exists('get_metal_name')) {
    function get_metal_name($metal) {
        $map = [
            'gold' => 'Gold',
            'silver' => 'Silver',
            'silver_925' => '925 Sterling Silver'
        ];
        return $map[$metal] ?? 'Gold';
    }
}

$items = null;
if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
    $items = $conn->query("SELECT * FROM jewellery_items WHERE COALESCE(is_sold_out, 0) = 0 ORDER BY id DESC");
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">
<channel>
    <title>ChandraKala Jewellers — Gujarat Official Product Feed</title>
    <link>https://www.chandrakalajewellers.in/</link>
    <description>100% BIS 916 Hallmarked Gold, Pure Silver, and 925 Sterling Silver Jewellery with live daily IST rates. Doorstep insured delivery across Gujarat, India.</description>
    <?php
    if ($items && $items->num_rows > 0) {
        while ($row = $items->fetch_assoc()) {
            $id = (int)$row['id'];
            $name = htmlspecialchars($row['name'], ENT_XML1);
            $metal = htmlspecialchars(get_metal_name($row['metal_type']), ENT_XML1);
            $weight = (float)$row['weight'];
            
            $rates_to_use = !empty($row['use_manual_rates']) ? $manual_rates : $standard_rates;
            $price = calculate_price($row['metal_type'], $weight, $rates_to_use);
            $roundedPrice = round($price);
            if ($roundedPrice <= 0) continue;

            $productUrl = htmlspecialchars(cj_abs_url('product?id=' . $id), ENT_XML1);
            $imgUrl = htmlspecialchars(cj_img_url($row['image'], 800), ENT_XML1);
            $availability = empty($row['is_sold_out']) ? 'in_stock' : 'out_of_stock';

            $desc = htmlspecialchars(
                $row['name'] . ' — ' . $metal . ' (' . $weight . 'g). 100% BIS Hallmarked with 6-digit HUID purity certificate. Family crafted by ChandraKala Jewellers, Gujarat. Doorstep insured delivery available across Ahmedabad, Surat, Vadodara, Rajkot, and all Gujarat districts.',
                ENT_XML1
            );
            ?>
    <item>
        <g:id>CJ-<?= $id ?></g:id>
        <g:title><?= $name ?> — <?= $metal ?></g:title>
        <g:description><?= $desc ?></g:description>
        <g:link><?= $productUrl ?></g:link>
        <g:image_link><?= $imgUrl ?></g:image_link>
        <g:availability><?= $availability ?></g:availability>
        <g:price><?= number_format($roundedPrice, 2, '.', '') ?> INR</g:price>
        <g:brand>ChandraKala Jewellers</g:brand>
        <g:condition>new</g:condition>
        <g:google_product_category>Apparel &amp; Accessories &gt; Jewelry</g:google_product_category>
        <g:product_type><?= $metal ?> Jewellery</g:product_type>
        <g:material><?= $metal ?></g:material>
        <g:shipping>
            <g:country>IN</g:country>
            <g:region>GJ</g:region>
            <g:service>Insured Express Delivery</g:service>
            <g:price>0.00 INR</g:price>
        </g:shipping>
    </item>
            <?php
        }
    }
    ?>
</channel>
</rss>
