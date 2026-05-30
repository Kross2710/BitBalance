<?php
/**
 * Horizontal calendar navbar (month selector + day strip).
 * Shared between dashboard.php (initial render) and
 * handlers/get_dashboard_day_data.php (AJAX re-render on month change).
 *
 * Requires: $selectedDate (Y-m-d).
 */
$selectedTime = strtotime($selectedDate);
$selectedYear = date('Y', $selectedTime);
$selectedMonth = date('m', $selectedTime);

$locale = current_locale();
if ($locale === 'vi') {
    $monthNames = [
        '01' => 'Tháng 1', '02' => 'Tháng 2', '03' => 'Tháng 3', '04' => 'Tháng 4',
        '05' => 'Tháng 5', '06' => 'Tháng 6', '07' => 'Tháng 7', '08' => 'Tháng 8',
        '09' => 'Tháng 9', '10' => 'Tháng 10', '11' => 'Tháng 11', '12' => 'Tháng 12'
    ];
    $monthDisplay = $monthNames[$selectedMonth] . ' ' . $selectedYear;
} else {
    $monthDisplay = date('F Y', $selectedTime);
}

// Previous / next month (first day of that month)
$prevMonthDate = date('Y-m-d', strtotime('first day of last month', strtotime("$selectedYear-$selectedMonth-01")));
$nextMonthDate = date('Y-m-d', strtotime('first day of next month', strtotime("$selectedYear-$selectedMonth-01")));

$daysInMonth = (int) date('t', strtotime("$selectedYear-$selectedMonth-01"));

// Disable forward navigation once we reach the current real month.
$todayYear = (int) date('Y');
$todayMonth = (int) date('n');
$selYearInt = (int) $selectedYear;
$selMonthInt = (int) $selectedMonth;
$nextMonthDisabled = ($selYearInt > $todayYear) || ($selYearInt === $todayYear && $selMonthInt >= $todayMonth);

$dayMap = [
    'Mon' => ['en' => 'M', 'vi' => 'T2'],
    'Tue' => ['en' => 'T', 'vi' => 'T3'],
    'Wed' => ['en' => 'W', 'vi' => 'T4'],
    'Thu' => ['en' => 'T', 'vi' => 'T5'],
    'Fri' => ['en' => 'F', 'vi' => 'T6'],
    'Sat' => ['en' => 'S', 'vi' => 'T7'],
    'Sun' => ['en' => 'S', 'vi' => 'CN'],
];
?>
<section class="calendar-navbar" data-selected="<?= htmlspecialchars($selectedDate) ?>">
    <div class="calendar-header">
        <div class="month-selector">
            <a href="?date=<?= $prevMonthDate ?>" class="btn-month-nav" data-date="<?= $prevMonthDate ?>" title="Previous Month">
                <i class="fas fa-chevron-left"></i>
            </a>
            <button type="button" class="month-title-btn" id="monthTitleBtn"
                data-year="<?= (int) $selectedYear ?>" data-month="<?= (int) $selectedMonth ?>"
                aria-haspopup="true" aria-expanded="false">
                <span><?= htmlspecialchars($monthDisplay) ?></span>
                <i class="fas fa-caret-down"></i>
            </button>
            <?php if ($nextMonthDisabled): ?>
                <span class="btn-month-nav disabled" title="Next Month" aria-disabled="true">
                    <i class="fas fa-chevron-right"></i>
                </span>
            <?php else: ?>
                <a href="?date=<?= $nextMonthDate ?>" class="btn-month-nav" data-date="<?= $nextMonthDate ?>" title="Next Month">
                    <i class="fas fa-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>

        <!-- Month/Year picker (populated by JS on open) -->
        <div class="month-picker" id="monthPicker" hidden></div>
    </div>

    <div class="day-scroll">
        <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
            <?php
            $dayStr = sprintf('%04d-%02d-%02d', $selectedYear, $selectedMonth, $d);
            $dayTimestamp = strtotime($dayStr);
            $rawDayInitial = date('D', $dayTimestamp);
            $dayInitial = $dayMap[$rawDayInitial][$locale] ?? substr($rawDayInitial, 0, 1);

            $isActive = ($dayStr === $selectedDate);
            $isToday = ($dayStr === date('Y-m-d'));
            $isFuture = ($dayStr > date('Y-m-d'));

            $chipClasses = 'day-chip';
            if ($isActive) $chipClasses .= ' active';
            if ($isToday) $chipClasses .= ' today';
            if ($isFuture) $chipClasses .= ' future';
            ?>
            <?php if ($isFuture): ?>
                <div class="<?= $chipClasses ?>">
                    <span class="day-name"><?= htmlspecialchars($dayInitial) ?></span>
                    <span class="day-number"><?= $d ?></span>
                </div>
            <?php else: ?>
                <a href="?date=<?= $dayStr ?>" class="<?= $chipClasses ?>" data-date="<?= $dayStr ?>">
                    <span class="day-name"><?= htmlspecialchars($dayInitial) ?></span>
                    <span class="day-number"><?= $d ?></span>
                </a>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
</section>
