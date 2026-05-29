<?php
/**
 * Render a single intake log row as <tr>.
 *
 * Used by:
 *   - dashboard-intake.php (Today's History — no Date column)
 *   - dashboard-history.php (full history — Date column shown)
 *   - handlers/process_intake.php (AJAX response after Log Entry)
 *
 * Required:
 *   $entry — assoc array with keys:
 *              intakeLog_id, food_item, calories, meal_category, date_intake,
 *              protein, carbs, fat (macros may be missing → treated as 0)
 *
 * Optional:
 *   $showDate    — bool, default false. Prepend a Date <td> (history page).
 *   $timeLabel   — string override for the Time cell. Defaults to "H:i" of date_intake.
 *                  Used by process_intake.php to render "Just now" for fresh inserts.
 *   $hideActions — bool, default false. Replace edit/delete buttons with a lock
 *                  (guest demo rows that can't be mutated).
 */

$_entry = $entry ?? [];
$_showDate = !empty($showDate);
$_hideActions = !empty($hideActions);
$_timeLabel = $timeLabel ?? null;

$_p = (float) ($_entry['protein'] ?? 0);
$_c = (float) ($_entry['carbs']   ?? 0);
$_f = (float) ($_entry['fat']     ?? 0);
$_fmtNum = static function ($n) {
    $s = rtrim(rtrim(number_format($n, 1, '.', ''), '0'), '.');
    return $s === '' ? '0' : $s;
};
$_pD = $_fmtNum($_p);
$_cD = $_fmtNum($_c);
$_fD = $_fmtNum($_f);

$_catRaw   = strtolower($_entry['meal_category'] ?? '');
$_catCls   = 'cat-' . $_catRaw;
// Translate the category label when we recognise it; fall back to ucfirst
// of the raw value for custom/unknown categories.
$_catKey   = 'dashboard.meal.' . $_catRaw;
$_catLabel = function_exists('t_raw') && in_array($_catRaw, ['breakfast', 'lunch', 'dinner', 'snack'], true)
    ? t_raw($_catKey)
    : ucfirst($_catRaw);

$_dateIntake = $_entry['date_intake'] ?? null;
$_iso        = function_exists('toIsoVN') && $_dateIntake ? toIsoVN($_dateIntake) : ($_dateIntake ?? '');
$_ts         = $_dateIntake ? strtotime($_dateIntake) : time();
$_timeText   = $_timeLabel ?? date('H:i', $_ts);
?>
<tr data-id="<?= (int) ($_entry['intakeLog_id'] ?? 0) ?>"
    data-protein="<?= htmlspecialchars($_pD) ?>"
    data-carbs="<?= htmlspecialchars($_cD) ?>"
    data-fat="<?= htmlspecialchars($_fD) ?>">
    <?php if ($_showDate): ?>
        <td data-label="<?= t('intake.row.date') ?>" class="intake-date-cell" data-sort="<?= $_ts ?>">
            <div class="date-cell">
                <span class="day"   data-iso="<?= htmlspecialchars($_iso) ?>" data-tz-format="date-day"><?= date('d', $_ts) ?></span>
                <span class="month" data-iso="<?= htmlspecialchars($_iso) ?>" data-tz-format="date-monthyear"><?= date('M Y', $_ts) ?></span>
            </div>
        </td>
    <?php endif; ?>

    <td data-label="<?= t('intake.row.food') ?>" class="fw-bold intake-food-cell">
        <?= htmlspecialchars(ucfirst($_entry['food_item'] ?? '')) ?>
    </td>

    <td data-label="<?= t('intake.row.calories') ?>" class="text-primary cal-cell intake-cal-cell">
        <span class="cal-val"><?= htmlspecialchars((string) ($_entry['calories'] ?? 0)) ?></span> <?= t('common.kcal') ?>
    </td>

    <td data-label="<?= t('intake.row.macros') ?>" class="macros-cell intake-macros-cell">
        <span class="macro-chip p">P <?= $_pD ?></span>
        <span class="macro-chip c">C <?= $_cD ?></span>
        <span class="macro-chip f">F <?= $_fD ?></span>
    </td>

    <td data-label="<?= t('intake.row.category') ?>" class="intake-category-cell">
        <span class="cat-badge <?= $_catCls ?>"><?= htmlspecialchars($_catLabel) ?></span>
    </td>

    <td data-label="<?= t('intake.row.time') ?>" class="text-muted intake-time-cell"
        data-iso="<?= htmlspecialchars($_iso) ?>"
        data-tz-format="time">
        <?= htmlspecialchars($_timeText) ?>
    </td>

    <td data-label="<?= t('intake.row.action') ?>" class="row-actions-cell">
        <div class="row-actions">
        <?php if ($_hideActions): ?>
            <span class="row-action-lock" title="<?= t('intake.row.lock_title') ?>">
                <i class="fas fa-lock"></i>
            </span>
        <?php else: ?>
            <button type="button" class="btn-quick-log btnLogAgain" title="<?= t('intake.row.log_again_title') ?>">
                <i class="fas fa-plus"></i>
            </button>
            <button type="button" class="btn-edit" title="<?= t('intake.row.edit_title') ?>">
                <i class="fas fa-edit"></i>
            </button>
            <button type="button" class="btn-delete deleteBtn" title="<?= t('intake.row.delete_title') ?>">
                <i class="fas fa-trash-alt"></i>
            </button>
        <?php endif; ?>
        </div>
    </td>
</tr>
