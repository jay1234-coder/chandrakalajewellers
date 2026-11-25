<?php
require_once 'config/db.php';
require_once 'includes/pricing.php';
require_once 'includes/seo.php';

$activePage = 'mcx';
$b = cj_business_info();
$standard_rates = cj_get_standard_rates($conn);

$gold1g = (float)($standard_rates['gold_rate'] ?? 0);
$gold10g = $gold1g * 10;
$todayDateLabel = cj_format_ist(time(), 'd M Y', false);

$seo = cj_merge_seo([
    'title' => 'MCX Gold Rate Calculator | ChandraKala Jewellers Gujarat',
    'description' => 'Real-time MCX Gold Rate Calculator: convert MCX bullion rate with 0.996/0.995 divisor, 3% GST, delivery charges, and customizable dynamic brokerage. By ChandraKala Jewellers, Khedbrahma, Gujarat.',
    'keywords' => 'MCX gold rate calculator, gold calculator Gujarat, MCX to physical gold rate, bullion calculator India, 996 gold rate calculator, 995 gold rate, ChandraKala Jewellers MCX, gold rate Khedbrahma',
    'canonical' => cj_abs_url('mcx'),
    'modified_iso' => cj_format_ist_iso($standard_rates['updated_at'] ?? null),
    'schema' => [
        cj_local_business_schema(),
        cj_breadcrumb_schema([
            ['name' => 'Home', 'url' => cj_abs_url('index1')],
            ['name' => 'MCX Gold Rate Calculator', 'url' => cj_abs_url('mcx')],
        ]),
        cj_faq_schema([
            [
                'q' => 'How is the final gold rate calculated from MCX?',
                'a' => 'The calculation multiplies the MCX rate by 10, divides by the purity factor (0.996 or 0.995), adds 3% GST, delivery charge (0.118%), dynamic brokerage (default ₹500), and fixed delivery charge (₹295) to calculate the final rate per 10g.'
            ],
            [
                'q' => 'Can I change the brokerage field in this calculator?',
                'a' => 'Yes! The brokerage field is fully dynamic and editable. You can adjust the brokerage amount to any custom value and the total and final rate per 10g will recalculate instantly.'
            ]
        ]),
    ],
]);
?>
<!DOCTYPE html>
<html lang="en-IN">
<head>
<?php include 'includes/assets-head.php'; ?>
<?php include 'includes/seo-head.php'; ?>
<style>
.mcx-hero {
    text-align: center;
    padding: 32px 16px 20px;
    max-width: 800px;
    margin: 0 auto;
}
.mcx-hero h1 {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: clamp(24px, 4vw, 36px);
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 10px;
}
.mcx-hero p {
    font-size: 15px;
    line-height: 1.6;
    color: var(--text-secondary);
    margin: 0 auto 16px;
}
.mcx-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--primary-gold-dark, #b8972c);
    background: rgba(212, 175, 55, 0.12);
    border: 1px solid rgba(212, 175, 55, 0.3);
    padding: 6px 16px;
    border-radius: 999px;
    margin-bottom: 12px;
}
html[data-theme="dark"] .mcx-badge {
    color: var(--primary-gold, #f0d78c);
    background: rgba(240, 215, 140, 0.12);
    border-color: rgba(240, 215, 140, 0.3);
}

.mcx-wrapper {
    max-width: 620px;
    margin: 0 auto 48px;
    padding: 0 12px;
}

.calculator-card {
    background: var(--bg-card, #ffffff);
    border: 1px solid rgba(212, 175, 55, 0.3);
    border-radius: 20px;
    padding: 32px 28px;
    box-shadow: 0 10px 30px -10px rgba(212, 175, 55, 0.15), 0 4px 16px rgba(0, 0, 0, 0.04);
    position: relative;
    overflow: hidden;
    transition: var(--transition-smooth);
}
html[data-theme="dark"] .calculator-card {
    background: #161a22;
    border-color: rgba(240, 215, 140, 0.25);
    box-shadow: 0 12px 36px -12px rgba(0, 0, 0, 0.6);
}
.calculator-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 10%;
    right: 10%;
    height: 2px;
    background: linear-gradient(90deg, transparent, var(--primary-gold, #d4af37), transparent);
}

.calc-section-title {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 19px;
    font-weight: 700;
    color: var(--text-primary);
    margin: 24px 0 16px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.input-group, .result-group {
    display: flex;
    flex-direction: column;
    margin-bottom: 16px;
}
.input-group label, .result-group label {
    font-size: 13.5px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.input-group label .hint, .result-group label .hint {
    font-size: 11px;
    font-weight: 500;
    color: var(--text-secondary);
}

.calc-input {
    width: 100%;
    padding: 12px 14px;
    border: 1.5px solid var(--border-color, #e5e7eb);
    border-radius: 10px;
    font-size: 16px;
    font-family: inherit;
    background-color: var(--bg-light, #f9fafb);
    color: var(--text-primary, #111827);
    box-sizing: border-box;
    transition: all 0.2s ease;
}
.calc-input:focus {
    outline: none;
    border-color: var(--primary-gold, #d4af37);
    box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.2);
    background-color: var(--bg-card, #ffffff);
}
.calc-input[readonly] {
    background-color: rgba(0, 0, 0, 0.025);
    color: var(--text-secondary);
    cursor: default;
}
html[data-theme="dark"] .calc-input[readonly] {
    background-color: rgba(255, 255, 255, 0.03);
    color: #94a3b8;
}

/* Dynamic Brokerage Highlight */
.calc-input.is-editable-dynamic {
    background-color: var(--bg-card, #ffffff);
    border: 1.5px solid var(--primary-gold-dark, #b8972c);
    font-weight: 600;
    color: var(--text-primary);
}
.calc-input.is-editable-dynamic:focus {
    border-color: var(--primary-gold, #d4af37);
    box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.25);
}

/* Radio button group */
.radio-group-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-top: 4px;
}
.radio-pill {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 14px;
    border: 1.5px solid var(--border-color);
    border-radius: 10px;
    background: var(--bg-card);
    cursor: pointer;
    font-weight: 600;
    font-size: 15px;
    color: var(--text-primary);
    transition: all 0.2s ease;
}
.radio-pill:hover {
    border-color: var(--primary-gold);
}
.radio-pill input[type="radio"] {
    accent-color: var(--primary-gold-dark);
    width: 17px;
    height: 17px;
    cursor: pointer;
}
.radio-pill.is-selected {
    border-color: var(--primary-gold);
    background: rgba(212, 175, 55, 0.08);
    color: var(--primary-gold-dark);
}

/* Action Buttons */
.calc-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin: 22px 0 10px;
}
.btn-calc {
    padding: 13px 20px;
    background: linear-gradient(135deg, #d4af37 0%, #b8972c 100%);
    color: #ffffff;
    border: none;
    border-radius: 30px;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(212, 175, 55, 0.35);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.btn-calc:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(212, 175, 55, 0.45);
}
.btn-reset {
    padding: 13px 20px;
    background: transparent;
    color: var(--text-secondary);
    border: 1.5px solid var(--border-color);
    border-radius: 30px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.btn-reset:hover {
    background: rgba(0, 0, 0, 0.04);
    color: var(--text-primary);
}

/* Results Grid & Highlights */
.result-group.total-value .calc-input,
.result-group.final-value .calc-input {
    font-size: 18px;
    font-weight: 700;
    color: var(--primary-gold-dark);
    background: rgba(212, 175, 55, 0.08);
    border-color: rgba(212, 175, 55, 0.4);
}
.result-group.final-value .calc-input {
    font-size: 20px;
    color: #10b981;
    background: rgba(16, 185, 129, 0.08);
    border-color: rgba(16, 185, 129, 0.35);
}
html[data-theme="dark"] .result-group.final-value .calc-input {
    color: #34d399;
}

.calc-footer-notes {
    margin-top: 24px;
    padding: 14px 16px;
    border-radius: 12px;
    background: rgba(212, 175, 55, 0.06);
    border: 1px solid rgba(212, 175, 55, 0.2);
    font-size: 12.5px;
    line-height: 1.5;
    color: var(--text-secondary);
}

.share-strip {
    display: flex;
    justify-content: center;
    gap: 12px;
    margin-top: 20px;
}
.btn-whatsapp-share {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: 999px;
    background-color: #25d366;
    color: #ffffff;
    text-decoration: none;
    font-size: 13.5px;
    font-weight: 600;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.btn-whatsapp-share:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(37, 211, 102, 0.35);
}

@media (max-width: 520px) {
    .calculator-card {
        padding: 24px 16px;
        border-radius: 16px;
    }
    .calc-actions {
        grid-template-columns: 1fr;
    }
}
</style>
</head>
<body>

<?php include 'includes/header.php'; ?>

<main class="container">
    <div class="mcx-hero reveal">
        <span class="mcx-badge">⚡ Real-Time Bullion Conversion</span>
        <h1>Gold MCX Rate Calculator ✨</h1>
        <p>Convert MCX gold futures rate to physical gold rate with custom purity division (0.996 / 0.995), 3% GST, standard delivery, and dynamic brokerage.</p>
        <?php if ($gold10g > 0): ?>
            <p style="font-size: 13px; color: var(--primary-gold-dark); font-weight: 600;">
                Today's Store Bullion Rate (<?= htmlspecialchars($todayDateLabel) ?>): ₹<?= number_format($gold10g, 2) ?> / 10g
            </p>
        <?php endif; ?>
    </div>

    <div class="mcx-wrapper reveal">
        <div class="calculator-card">
            <div class="input-group">
                <label for="gold-mcx-rate">
                    <span>Gold MCX Rate</span>
                    <span class="hint">Per 10g Base Rate</span>
                </label>
                <input type="number" id="gold-mcx-rate" class="calc-input" placeholder="e.g., 109260" step="any">
            </div>

            <div class="input-group">
                <label for="multiplied-value">
                    <span>*10 Value</span>
                    <span class="hint">(MCX Rate &times; 10)</span>
                </label>
                <input type="text" id="multiplied-value" class="calc-input" readonly placeholder="Calculated automatically">
            </div>

            <div class="input-group">
                <label>
                    <span>Divide by Purity Factor:</span>
                    <span class="hint">Select 0.996 or 0.995</span>
                </label>
                <div class="radio-group-container">
                    <label class="radio-pill is-selected" id="pill-996">
                        <input type="radio" id="divide-996" name="divide-option" value="0.996" checked>
                        <span>0.996</span>
                    </label>
                    <label class="radio-pill" id="pill-995">
                        <input type="radio" id="divide-995" name="divide-option" value="0.995">
                        <span>0.995</span>
                    </label>
                </div>
            </div>

            <div class="calc-actions">
                <button type="button" class="btn-calc" onclick="calculateMCX()">
                    <span>Calculate Now</span>
                    <span>&rarr;</span>
                </button>
                <button type="button" class="btn-reset" onclick="resetMCX()">
                    <span>Reset Fields</span>
                </button>
            </div>

            <div class="calc-section-title">
                <span>Calculation Results</span>
                <span style="font-size: 12px; font-weight: 500; color: var(--text-secondary);">INR (₹)</span>
            </div>

            <div class="result-group">
                <label for="divided-value">Divided Value:</label>
                <input type="text" id="divided-value" class="calc-input" readonly>
            </div>

            <div class="result-group">
                <label for="gst-value">GST (3%):</label>
                <input type="text" id="gst-value" class="calc-input" readonly>
            </div>

            <div class="result-group">
                <label for="delivery-charge">Delivery Charge (0.118%):</label>
                <input type="text" id="delivery-charge" class="calc-input" readonly>
            </div>

            <!-- Dynamic Brokerage Field (Editable by user, defaults to 500) -->
            <div class="result-group">
                <label for="brokerage">
                    <span>Brokerage (Dynamic):</span>
                    <span class="hint" style="color:var(--primary-gold-dark);font-weight:700;">Editable field</span>
                </label>
                <input type="number" id="brokerage" class="calc-input is-editable-dynamic" value="500" step="any" placeholder="Enter custom brokerage, e.g. 500">
            </div>

            <div class="result-group">
                <label for="fixed-delivery">Fixed Delivery Charge:</label>
                <input type="text" id="fixed-delivery" class="calc-input" value="295" readonly>
            </div>

            <div class="result-group total-value">
                <label for="total-value">Total Value (Sum of all charges):</label>
                <input type="text" id="total-value" class="calc-input" readonly>
            </div>

            <div class="result-group final-value">
                <label for="final-value">Final Value (Per 10g Gold Rate):</label>
                <input type="text" id="final-value" class="calc-input" readonly>
            </div>

            <div class="calc-footer-notes">
                <strong>Formula:</strong> (MCX &times; 10 &divide; Divisor) + 3% GST + Delivery (0.118%) + Brokerage (Dynamic) + Fixed Delivery (₹295). Final Value is Total &divide; 10.
            </div>

            <div class="share-strip">
                <a id="btn-share-wa" href="https://wa.me/919427080359?text=Hello%20ChandraKala%20Jewellers%2C%20I%20used%20your%20MCX%20Gold%20Calculator" target="_blank" rel="noopener" class="btn-whatsapp-share">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                    <span>Share / Inquire on WhatsApp</span>
                </a>
            </div>
        </div>
    </div>
</main>

<script>
function calculateMCX() {
    const mcxInput = document.getElementById('gold-mcx-rate');
    const goldMcxRate = parseFloat(mcxInput ? mcxInput.value : 0);

    if (isNaN(goldMcxRate) || goldMcxRate <= 0) {
        return;
    }

    // 1. Multiply by 10
    const multipliedValue = goldMcxRate * 10;
    document.getElementById('multiplied-value').value = multipliedValue.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    // 2. Divisor (0.996 or 0.995)
    const selectedRadio = document.querySelector('input[name="divide-option"]:checked');
    const divisor = selectedRadio ? parseFloat(selectedRadio.value) : 0.996;
    const dividedValue = multipliedValue / divisor;
    document.getElementById('divided-value').value = dividedValue.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    // 3. GST (3%)
    const gst = dividedValue * 0.03;
    document.getElementById('gst-value').value = gst.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    // 4. Delivery Charge (0.118%)
    const deliveryCharge = multipliedValue * 0.00118;
    document.getElementById('delivery-charge').value = deliveryCharge.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    // 5. Dynamic Brokerage (editable, default 500) & Fixed Delivery (295)
    const brokerageField = document.getElementById('brokerage');
    const brokerageVal = brokerageField ? parseFloat(brokerageField.value) : 500;
    const brokerage = isNaN(brokerageVal) ? 0 : brokerageVal;
    const fixedDeliveryCharge = 295;

    // 6. Total Value
    const totalValue = dividedValue + gst + deliveryCharge + brokerage + fixedDeliveryCharge;
    document.getElementById('total-value').value = '₹' + totalValue.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    // 7. Final Value per 10g
    const finalValue = totalValue / 10;
    document.getElementById('final-value').value = '₹' + finalValue.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' / 10g';

    // Update WhatsApp link with details
    const waLink = document.getElementById('btn-share-wa');
    if (waLink) {
        const msg = encodeURIComponent(
            "Hello ChandraKala Jewellers, here is my MCX Gold Rate calculation:\n" +
            "• Gold MCX Rate: ₹" + goldMcxRate.toFixed(2) + "\n" +
            "• Purity Divisor: " + divisor + "\n" +
            "• Brokerage: ₹" + brokerage.toFixed(2) + "\n" +
            "• Total: ₹" + totalValue.toFixed(2) + "\n" +
            "• Final Rate (per 10g): ₹" + finalValue.toFixed(2) + "\n" +
            "Calculated via chandrakalajewellers.in/mcx"
        );
        waLink.href = "https://wa.me/919427080359?text=" + msg;
    }
}

function resetMCX() {
    document.getElementById('gold-mcx-rate').value = '';
    document.getElementById('multiplied-value').value = '';
    document.getElementById('divided-value').value = '';
    document.getElementById('gst-value').value = '';
    document.getElementById('delivery-charge').value = '';
    document.getElementById('brokerage').value = '500';
    document.getElementById('total-value').value = '';
    document.getElementById('final-value').value = '';
    document.getElementById('divide-996').checked = true;
    updateRadioPills();
}

function updateRadioPills() {
    const r996 = document.getElementById('divide-996');
    const pill996 = document.getElementById('pill-996');
    const pill995 = document.getElementById('pill-995');
    if (r996 && pill996 && pill995) {
        if (r996.checked) {
            pill996.classList.add('is-selected');
            pill995.classList.remove('is-selected');
        } else {
            pill995.classList.add('is-selected');
            pill996.classList.remove('is-selected');
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const mcxInput = document.getElementById('gold-mcx-rate');
    const brokerageInput = document.getElementById('brokerage');
    const radios = document.querySelectorAll('input[name="divide-option"]');

    if (mcxInput) {
        mcxInput.addEventListener('input', calculateMCX);
        mcxInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') calculateMCX();
        });
    }
    if (brokerageInput) {
        brokerageInput.addEventListener('input', calculateMCX);
        brokerageInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') calculateMCX();
        });
    }
    radios.forEach(function(r) {
        r.addEventListener('change', function() {
            updateRadioPills();
            calculateMCX();
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
</body>
</html>
