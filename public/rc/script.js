function calculate() {
    // Get the Gold MCX Rate from the input field
    const goldMcxRate = parseFloat(document.getElementById('gold-mcx-rate').value);

    // Check if the input is a valid number
    if (isNaN(goldMcxRate)) {
        alert("Please enter a valid number for the Gold MCX Rate.");
        return;
    }

    // 1. Calculate the value after multiplying by 10
    const multipliedValue = goldMcxRate * 10;
    document.getElementById('multiplied-value').value = multipliedValue.toFixed(2);

    // 2. Determine the divisor based on the selected radio button
    const divisorOption = document.querySelector('input[name="divide-option"]:checked').value;
    const dividedValue = multipliedValue / parseFloat(divisorOption);
    document.getElementById('divided-value').value = dividedValue.toFixed(2);

    // 3. Calculate GST
    const gst = dividedValue * 0.03;
    document.getElementById('gst-value').value = gst.toFixed(2);

    // 4. Calculate Delivery Charge
    const deliveryCharge = multipliedValue * 0.00118;
    document.getElementById('delivery-charge').value = deliveryCharge.toFixed(2);

    // 5. Get dynamic Brokerage (editable by user, defaults to 500) and Fixed Delivery Charge
    const brokerageInput = parseFloat(document.getElementById('brokerage').value);
    const brokerage = isNaN(brokerageInput) ? 0 : brokerageInput;
    const fixedDeliveryCharge = 295;

    // 6. Calculate the Total Value
    const totalValue = dividedValue + gst + deliveryCharge + brokerage + fixedDeliveryCharge;
    document.getElementById('total-value').value = totalValue.toFixed(2);

    // 7. Calculate the Final Value
    const finalValue = totalValue / 10;
    document.getElementById('final-value').value = finalValue.toFixed(2);
}

// Auto-recalculate on input change for responsive UX
document.addEventListener('DOMContentLoaded', function() {
    const mcxInput = document.getElementById('gold-mcx-rate');
    const brokerageInput = document.getElementById('brokerage');
    const radioOptions = document.querySelectorAll('input[name="divide-option"]');

    function tryCalculate() {
        if (mcxInput && mcxInput.value.trim() !== '') {
            calculate();
        }
    }

    if (mcxInput) {
        mcxInput.addEventListener('input', tryCalculate);
        mcxInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') calculate();
        });
    }
    if (brokerageInput) {
        brokerageInput.addEventListener('input', tryCalculate);
        brokerageInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') calculate();
        });
    }
    radioOptions.forEach(function(radio) {
        radio.addEventListener('change', tryCalculate);
    });
});