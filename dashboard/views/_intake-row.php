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
 */

$_entry = $entry ?? [];
$_showDate = !empty($showDate);
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
$_catLabel = ucfirst($_catRaw);

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
        <td data-label="Date" data-sort="<?= $_ts ?>">
            <div class="date-cell">
                <span class="day"   data-iso="<?= htmlspecialchars($_iso) ?>" data-tz-format="date-day"><?= date('d', $_ts) ?></span>
                <span class="month" data-iso="<?= htmlspecialchars($_iso) ?>" data-tz-format="date-monthyear"><?= date('M Y', $_ts) ?></span>
            </div>
        </td>
    <?php endif; ?>

    <td data-label="Food" class="fw-bold">
        <?= htmlspecialchars(ucfirst($_entry['food_item'] ?? '')) ?>
    </td>

    <td data-label="Calories" class="text-primary cal-cell">
        <span class="cal-val"><?= htmlspecialchars((string) ($_entry['calories'] ?? 0)) ?></span> kcal
    </td>

    <td data-label="Macros" class="macros-cell">
        <span class="macro-chip p">P <?= $_pD ?>g</span>
        <span class="macro-chip c">C <?= $_cD ?>g</span>
        <span class="macro-chip f">F <?= $_fD ?>g</span>
    </td>

    <td data-label="Category">
        <span class="cat-badge <?= $_catCls ?>"><?= htmlspecialchars($_catLabel) ?></span>
    </td>

    <td data-label="Time" class="text-muted"
        data-iso="<?= htmlspecialchars($_iso) ?>"
        data-tz-format="time">
        <?= htmlspecialchars($_timeText) ?>
    </td>

    <td style="text-align: right;">
        <button type="button" class="btn-edit" title="Edit Entry">
            <i class="fas fa-edit"></i>
        </button>
        <button type="button" class="btn-delete deleteBtn" title="Delete Entry">
            <i class="fas fa-trash-alt"></i>
        </button>
    </td>
</tr>
