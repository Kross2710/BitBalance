<?php
/**
 * Reusable macro-balance editor (ring + 3 sliders). Carbs/Fat/Protein percentages
 * always total 100 (auto-rebalanced client-side). Shows % / grams / kcal live.
 *
 * Set before include (all optional):
 *   $mbId       string  unique id for the container (default 'macroBalance')
 *   $mbCalories int     calorie base used to derive grams/kcal (default 0)
 *   $mbPct      array   initial split ['carbs'=>45,'fat'=>25,'protein'=>30]
 *
 * Consumers read the chosen split client-side:
 *   const inst = MacroBalance.mount(document.getElementById('<id>'));
 *   inst.getGrams();  // {carbs, fat, protein}
 *   inst.setCalories(n);  inst.setPct({...});
 *
 * Requires css/components/macro-balance.css + js/macro-balance.js on the page.
 */
$mbId   = isset($mbId) ? $mbId : 'macroBalance';
$mbCals = (int) (isset($mbCalories) ? $mbCalories : 0);
$mbPct  = isset($mbPct) ? $mbPct : ['carbs' => 45, 'fat' => 25, 'protein' => 30];
$mbRows = [
    'carbs'   => t('dashboard.macros.carbs'),
    'fat'     => t('dashboard.macros.fat'),
    'protein' => t('dashboard.macros.protein'),
];
?>
<div class="macro-balance" id="<?= htmlspecialchars($mbId, ENT_QUOTES) ?>" data-calories="<?= $mbCals ?>">
    <div class="mb-ring-wrap">
        <div class="mb-ring"></div>
        <div class="mb-ring-center"><span class="mb-ring-pct">100%</span></div>
    </div>
    <p class="mb-hint"><?= t('macrobalance.hint') ?></p>
    <div class="mb-rows">
        <?php foreach ($mbRows as $__key => $__label): ?>
            <div class="mb-row mb-row--<?= $__key ?>" data-macro="<?= $__key ?>">
                <div class="mb-row-head">
                    <span class="mb-row-label"><?= htmlspecialchars($__label) ?></span>
                    <span class="mb-row-stats">
                        <b class="mb-pct">0%</b> &middot; <span class="mb-g">0g</span> &middot; <span class="mb-kcal">0 <?= t('common.kcal') ?></span>
                    </span>
                </div>
                <div class="mb-row-control">
                    <button type="button" class="mb-step" data-dir="-1" aria-label="-">&minus;</button>
                    <input type="range" class="mb-slider" min="0" max="100" step="1"
                        value="<?= (int) (isset($mbPct[$__key]) ? $mbPct[$__key] : 0) ?>"
                        aria-label="<?= htmlspecialchars($__label, ENT_QUOTES) ?>">
                    <button type="button" class="mb-step" data-dir="1" aria-label="+">+</button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
