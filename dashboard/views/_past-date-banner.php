<?php
/**
 * Layer-1 anti-confusion banner for the Intake page.
 *
 * Shown whenever the user is logging to a PAST day (selected on the Overview
 * calendar and carried over via ?date=). It stays pinned above the logging UI
 * — not a transient toast — so the user can never mistake a backdated session
 * for "today". Includes a one-tap escape back to today.
 *
 * Expects: $selectedDate (Y-m-d). Caller only includes this when
 *          $selectedDate !== today (and the user is logged in).
 */
$__ts = strtotime($selectedDate);
$__dateLabel = date('j/n/Y', $__ts); // numeric, locale-neutral (e.g. 28/5/2026)
$__daysAgo = (int) round((strtotime(date('Y-m-d')) - $__ts) / 86400);
$__relative = $__daysAgo === 1
    ? t('intake.past_banner.yesterday')
    : t('intake.past_banner.days_ago', ['n' => $__daysAgo]);
?>
<style>
    .past-date-banner {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        padding: 12px 16px;
        margin-bottom: 20px;
        border: 2px solid var(--color-warning, #f59e0b);
        border-radius: var(--radius-lg, 12px);
        background: var(--color-warning-surface, #fff7ed);
        color: var(--color-warning-text, #92400e);
        box-shadow: 0 4px 0 var(--color-border-subtle, rgba(0, 0, 0, .06));
        font-size: 0.95rem;
    }
    .past-date-banner__icon { font-size: 1.1rem; flex-shrink: 0; }
    .past-date-banner__text { flex: 1; min-width: 200px; }
    .past-date-banner__text strong { font-weight: 700; }
    .past-date-banner__back {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 14px;
        border-radius: var(--radius-md, 8px);
        background: var(--color-warning, #f59e0b);
        color: #fff;
        font-weight: 600;
        text-decoration: none;
        white-space: nowrap;
    }
    .past-date-banner__back:hover { filter: brightness(.95); }
</style>
<div class="past-date-banner" role="status">
    <i class="fas fa-clock-rotate-left past-date-banner__icon" aria-hidden="true"></i>
    <span class="past-date-banner__text">
        <?= t('intake.past_banner.logging_for') ?>
        <strong><?= htmlspecialchars($__dateLabel) ?></strong>
        (<?= htmlspecialchars($__relative) ?>) — <?= t('intake.past_banner.not_today') ?>
    </span>
    <a href="<?= BASE_URL ?>dashboard/dashboard-intake.php" class="past-date-banner__back">
        <i class="fas fa-rotate-left" aria-hidden="true"></i> <?= t('intake.past_banner.back_to_today') ?>
    </a>
</div>
