<?php
/**
 * Shared client-side helpers for the intake list + Edit Intake Entry modal.
 *
 * Exposes a single global `window.IntakeRow` namespace with order-independent
 * helpers (use semantic cell classes, never numeric column indexes).
 *
 * Include AFTER the modal partial and the table markup are emitted. Each page
 * is still responsible for wiring its own openers / submit flow — see
 * dashboard-intake.php (vanilla JS) and dashboard-history.php (jQuery+DataTables).
 */
?>
<script>
(function () {
    if (window.IntakeRow) return;

    function asEl(row) {
        return (row && typeof row.length === 'number' && row[0]) ? row[0] : row;
    }

    function pick(a, b) {
        return (a !== undefined && a !== null && a !== '') ? a : b;
    }

    function fmtMacro(v) {
        const n = parseFloat(v) || 0;
        if (Number.isInteger(n)) return String(n);
        return n.toFixed(1).replace(/\.0$/, '');
    }

    // Parse macros from the visible chips: "P 5g", "C 38.5g", ...
    // Used as fallback when row.dataset.protein/carbs/fat is missing.
    function macrosFromChips(rowEl) {
        const out = { protein: '', carbs: '', fat: '' };
        rowEl.querySelectorAll('.intake-macros-cell .macro-chip, td[data-label="Macros"] .macro-chip').forEach(chip => {
            const m = chip.textContent.trim().match(/([PCF])\s*([\d.]+)/i);
            if (!m) return;
            const k = m[1].toUpperCase() === 'P' ? 'protein'
                    : m[1].toUpperCase() === 'C' ? 'carbs' : 'fat';
            out[k] = m[2];
        });
        return out;
    }

    function categoryFromBadge(rowEl) {
        const badge = rowEl.querySelector('.intake-category-cell .cat-badge, td[data-label="Category"] .cat-badge');
        if (!badge) return 'breakfast';
        let cat = 'breakfast';
        badge.classList.forEach(cls => {
            if (cls.startsWith('cat-') && cls !== 'cat-badge') {
                cat = cls.slice(4);
            }
        });
        return cat;
    }

    const IntakeRow = {
        /**
         * Fill the Edit Intake Entry modal form from a table row.
         * Accepts a DOM element OR a jQuery wrapper.
         */
        fillEditForm(row) {
            const rowEl = asEl(row);
            if (!rowEl) return;

            const foodCell = rowEl.querySelector('.intake-food-cell, td[data-label="Food"]');
            const calCell  = rowEl.querySelector('.intake-cal-cell, td[data-label="Calories"]');
            const food     = foodCell ? foodCell.innerText.trim() : '';
            const calText  = calCell  ? calCell.innerText.trim() : '';
            const calories = parseInt(calText.replace(/\D/g, ''), 10) || 0;
            const chips    = macrosFromChips(rowEl);

            const set = (id, v) => {
                const el = document.getElementById(id);
                if (el) el.value = v;
            };

            set('edit_intake_id',    rowEl.dataset.id || '');
            set('edit_food_item',    food);
            set('edit_calories',     calories);
            set('edit_meal_category', categoryFromBadge(rowEl));
            set('edit_protein', pick(rowEl.dataset.protein, chips.protein));
            set('edit_carbs',   pick(rowEl.dataset.carbs,   chips.carbs));
            set('edit_fat',     pick(rowEl.dataset.fat,     chips.fat));
        },

        /**
         * Patch a table row with the data returned from edit_intake.php.
         * Updates food / calories / macros / category cells, the data-* attrs,
         * and briefly flashes the row green.
         */
        updateRow(row, data) {
            const rowEl = asEl(row);
            if (!rowEl || !data) return;

            const foodCell = rowEl.querySelector('.intake-food-cell, td[data-label="Food"]');
            if (foodCell) foodCell.innerText = data.food_item;

            const calCell = rowEl.querySelector('.intake-cal-cell, td[data-label="Calories"]');
            if (calCell) {
                // Preserve the <span class="cal-val"> wrapper if the page uses it.
                if (calCell.querySelector('.cal-val')) {
                    calCell.innerHTML = `<span class="cal-val">${data.calories}</span> kcal`;
                } else {
                    calCell.innerText = data.calories + ' kcal';
                }
            }

            const pD = fmtMacro(data.protein);
            const cD = fmtMacro(data.carbs);
            const fD = fmtMacro(data.fat);
            const macroCell = rowEl.querySelector('.intake-macros-cell, td[data-label="Macros"]');
            if (macroCell) {
                macroCell.innerHTML =
                    `<span class="macro-chip p">P ${pD}g</span>` +
                    `<span class="macro-chip c">C ${cD}g</span>` +
                    `<span class="macro-chip f">F ${fD}g</span>`;
            }

            const catCell = rowEl.querySelector('.intake-category-cell, td[data-label="Category"]');
            if (catCell && data.meal_category) {
                const cat = data.meal_category;
                const label = cat.charAt(0).toUpperCase() + cat.slice(1);
                catCell.innerHTML = `<span class="cat-badge cat-${cat}">${label}</span>`;
            }

            rowEl.dataset.protein = pD;
            rowEl.dataset.carbs   = cD;
            rowEl.dataset.fat     = fD;

            rowEl.style.transition = 'background-color 0.3s';
            rowEl.style.backgroundColor = 'rgba(46, 204, 113, 0.2)';
            setTimeout(() => { rowEl.style.backgroundColor = ''; }, 500);
        },

        openModal() {
            const m = document.getElementById('editIntakeModal');
            if (m) m.style.display = 'block';
        },

        closeModal() {
            const m = document.getElementById('editIntakeModal');
            if (m) m.style.display = 'none';
        },

        /**
         * Convenience: wire up close-button + cancel-button + backdrop-click
         * handlers in one call. Pages still bind their own open trigger and
         * submit handler (since post-save logic differs per page).
         */
        bindCloseHandlers() {
            const modal  = document.getElementById('editIntakeModal');
            const close  = document.getElementById('closeEditModal');
            const cancel = document.getElementById('cancelEditBtn');
            const onClose = () => IntakeRow.closeModal();
            if (close)  close.addEventListener('click', onClose);
            if (cancel) cancel.addEventListener('click', onClose);
            if (modal) {
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) onClose();
                });
            }
        },
    };

    window.IntakeRow = IntakeRow;
})();
</script>
