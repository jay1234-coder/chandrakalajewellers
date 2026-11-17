/**
 * Must match includes/pricing.php (rates are per 1 gram, not per 10g).
 * Counted metal rate = board rate after rate_formula operator steps.
 * Default gold: rate × 0.9166 ÷ 1.03
 */
(function (global) {
    'use strict';

    var DEFAULT_FORMULA = {
        gold: [
            { op: '*', value: 0.9166 },
            { op: '/', value: 1.03 }
        ],
        silver: [],
        silver_925: [],
        copper: []
    };

    var ALLOWED_OPS = { '+': 1, '-': 1, '*': 1, '/': 1 };

    function num(value) {
        var n = parseFloat(value);
        return isNaN(n) ? 0 : n;
    }

    function makingPercent(rates) {
        if (!rates) return 0;
        if (Object.prototype.hasOwnProperty.call(rates, 'making_charges')) {
            return num(rates.making_charges);
        }
        if (Object.prototype.hasOwnProperty.call(rates, 'making_charges_percent')) {
            return num(rates.making_charges_percent);
        }
        if (Object.prototype.hasOwnProperty.call(rates, 'manual_making_charges_percent')) {
            return num(rates.manual_making_charges_percent);
        }
        return 0;
    }

    function applyMakingToSilver(rates) {
        return !!(rates && (rates.apply_making_to_silver || rates.manual_apply_making_to_silver));
    }

    function normalizeFormula(raw) {
        var out = { gold: [], silver: [], silver_925: [], copper: [] };
        if (!raw || typeof raw !== 'object') {
            return out;
        }
        Object.keys(out).forEach(function (metal) {
            var steps = raw[metal];
            if (!Array.isArray(steps)) return;
            steps.forEach(function (step) {
                if (!step || typeof step !== 'object') return;
                var op = String(step.op || '');
                if (!ALLOWED_OPS[op]) return;
                out[metal].push({ op: op, value: num(step.value) });
            });
        });
        return out;
    }

    function formulaMap(rates) {
        if (rates && Object.prototype.hasOwnProperty.call(rates, 'rate_formula')) {
            return normalizeFormula(rates.rate_formula);
        }
        if (global.CJ_RATE_FORMULA) {
            return normalizeFormula(global.CJ_RATE_FORMULA);
        }
        return JSON.parse(JSON.stringify(DEFAULT_FORMULA));
    }

    function cjApplyRateFormula(base, steps) {
        base = num(base);
        if (!Array.isArray(steps)) return base;
        for (var i = 0; i < steps.length; i++) {
            var step = steps[i] || {};
            var op = String(step.op || '');
            var value = num(step.value);
            if (op === '+') base += value;
            else if (op === '-') base -= value;
            else if (op === '*') base *= value;
            else if (op === '/') {
                if (Math.abs(value) < 1e-12) continue;
                base /= value;
            }
        }
        return base;
    }

    function metalBaseRate(metal, rates) {
        metal = String(metal || '').toLowerCase();
        if (!rates) return 0;
        if (metal === 'gold') return num(rates.gold_rate);
        if (metal === 'silver') return num(rates.silver_rate);
        if (metal === 'silver_925') return num(rates.silver_925_rate);
        if (metal === 'copper') return num(rates.copper_rate);
        return 0;
    }

    function cjEffectiveMetalRate(metal, rates) {
        var base = metalBaseRate(metal, rates);
        if (base <= 0) return 0;
        var map = formulaMap(rates);
        metal = String(metal || '').toLowerCase();
        return cjApplyRateFormula(base, map[metal] || []);
    }

    function cjCalculatePrice(metal, weight, rates) {
        weight = num(weight);
        if (weight <= 0 || !rates) {
            return 0;
        }

        metal = String(metal || '').toLowerCase();
        var base = cjEffectiveMetalRate(metal, rates);
        if (base <= 0) {
            return 0;
        }

        var price = base * weight;
        var applyMaking = metal === 'gold'
            || ((metal === 'silver' || metal === 'silver_925') && applyMakingToSilver(rates));

        if (applyMaking) {
            price += price * (makingPercent(rates) / 100);
        }

        return price;
    }

    global.cjApplyRateFormula = cjApplyRateFormula;
    global.cjEffectiveMetalRate = cjEffectiveMetalRate;
    global.cjCalculatePrice = cjCalculatePrice;
    global.CJ_DEFAULT_RATE_FORMULA = DEFAULT_FORMULA;
})(typeof window !== 'undefined' ? window : this);
