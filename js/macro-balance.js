/*
 * Macro Balance editor controller (reusable).
 *
 * Wires a .macro-balance container: 3 percentage sliders (carbs/fat/protein) edited
 * independently. The total may exceed or fall short of 100% — the user must balance
 * it back to exactly 100% before the consumer allows Save (isValid()). Live-updates
 * each row's % / grams / kcal and the donut ring (which shows a gap when under 100,
 * and adds an `mb-invalid` class on the root when total !== 100).
 *
 * grams: carbs & protein = kcal / 4, fat = kcal / 9.
 *
 * Usage:
 *   const inst = MacroBalance.mount(el, { onChange: st => { saveBtn.disabled = !st.valid; } });
 *   inst.getGrams();   // {carbs, fat, protein}
 *   inst.getTotal();   // sum of the three percentages
 *   inst.isValid();    // total === 100
 *   inst.setCalories(2000);
 *   inst.setPct({carbs:40, fat:30, protein:30});
 * onChange receives { grams, total, valid }.
 */
window.MacroBalance = (function () {
    'use strict';

    const ORDER = ['carbs', 'fat', 'protein'];
    const KCAL_PER_G = { carbs: 4, protein: 4, fat: 9 };
    const COLORS = { carbs: '#f4b740', fat: '#4aa3f0', protein: '#46c46a' };

    function mount(root, opts) {
        if (!root) return null;
        opts = opts || {};

        let calories = parseInt(root.getAttribute('data-calories'), 10) || opts.calories || 0;

        const rows = {};
        ORDER.forEach(function (m) {
            const el = root.querySelector('.mb-row[data-macro="' + m + '"]');
            if (!el) return;
            rows[m] = {
                el: el,
                slider: el.querySelector('.mb-slider'),
                pctEl: el.querySelector('.mb-pct'),
                gEl: el.querySelector('.mb-g'),
                kcalEl: el.querySelector('.mb-kcal'),
                pct: parseInt(el.querySelector('.mb-slider').value, 10) || 0,
            };
        });
        if (ORDER.some(function (m) { return !rows[m]; })) return null;

        const ring = root.querySelector('.mb-ring');
        const ringPct = root.querySelector('.mb-ring-pct');

        function normalize() {
            let sum = ORDER.reduce(function (s, m) { return s + rows[m].pct; }, 0);
            if (sum === 0) {
                rows.carbs.pct = 45; rows.fat.pct = 25; rows.protein.pct = 30;
                return;
            }
            if (sum === 100) return;
            let acc = 0;
            ORDER.forEach(function (m, i) {
                if (i < ORDER.length - 1) {
                    rows[m].pct = Math.round(rows[m].pct * 100 / sum);
                    acc += rows[m].pct;
                } else {
                    rows[m].pct = 100 - acc;
                }
            });
        }

        function total() {
            return ORDER.reduce(function (s, m) { return s + rows[m].pct; }, 0);
        }

        // Free edit: set only this macro. The total may go above/below 100 — the
        // consumer gates Save on isValid() (total === 100).
        function setMacro(target, val) {
            rows[target].pct = Math.max(0, Math.min(100, Math.round(val)));
            render();
        }

        function render() {
            let deg = 0;
            const stops = [];
            ORDER.forEach(function (m) {
                const r = rows[m];
                if (parseInt(r.slider.value, 10) !== r.pct) r.slider.value = r.pct;
                r.pctEl.textContent = r.pct + '%';
                const kcal = Math.round(calories * r.pct / 100);
                r.gEl.textContent = Math.round(kcal / KCAL_PER_G[m]) + 'g';
                r.kcalEl.textContent = kcal + ' kcal';
                const start = deg;
                const end = Math.min(360, deg + r.pct * 3.6);
                if (end > start) stops.push(COLORS[m] + ' ' + start + 'deg ' + end + 'deg');
                deg = end;
            });
            // When total < 100 the remainder shows as a track gap.
            if (deg < 360) stops.push('var(--color-border) ' + deg + 'deg 360deg');
            if (ring) ring.style.background = 'conic-gradient(' + stops.join(',') + ')';

            const t = total();
            if (ringPct) ringPct.textContent = t + '%';
            root.classList.toggle('mb-invalid', t !== 100);
            if (opts.onChange) opts.onChange({ grams: getGrams(), total: t, valid: t === 100 });
        }

        function getGrams() {
            const out = {};
            ORDER.forEach(function (m) {
                const kcal = Math.round(calories * rows[m].pct / 100);
                out[m] = Math.round(kcal / KCAL_PER_G[m]);
            });
            return out;
        }

        ORDER.forEach(function (m) {
            rows[m].slider.addEventListener('input', function () {
                setMacro(m, parseInt(rows[m].slider.value, 10) || 0);
            });
            rows[m].el.querySelectorAll('.mb-step').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    setMacro(m, rows[m].pct + (parseInt(btn.getAttribute('data-dir'), 10) || 0));
                });
            });
        });

        normalize();
        render();

        return {
            getGrams: getGrams,
            getTotal: total,
            isValid: function () { return total() === 100; },
            getPct: function () {
                return { carbs: rows.carbs.pct, fat: rows.fat.pct, protein: rows.protein.pct };
            },
            setCalories: function (n) {
                calories = parseInt(n, 10) || 0;
                render();
            },
            setPct: function (p) {
                rows.carbs.pct = parseInt(p.carbs, 10) || 0;
                rows.fat.pct = parseInt(p.fat, 10) || 0;
                rows.protein.pct = parseInt(p.protein, 10) || 0;
                normalize();
                render();
            },
        };
    }

    return { mount: mount };
})();
