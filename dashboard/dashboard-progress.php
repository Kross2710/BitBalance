<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../include/init.php';

$activePage = 'progress';
$activeHeader = 'dashboard';
$bodyClass = 'page-progress';
$displayUser = $isLoggedIn ? $user['user_name'] : 'Guest';

if (!$isLoggedIn) {
    ?>
    <!DOCTYPE html>
    <html lang="<?= html_lang_attr() ?>" data-theme="<?= htmlspecialchars($_SESSION['user']['theme_preference'] ?? 'system', ENT_QUOTES) ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= t('progress.title_alt') ?></title>
        <?php
        $pageComponents = ['sidebar'];
        $pageCss = ['css/dashboard.css', 'css/pages/dashboard-progress.css'];
        include PROJECT_ROOT . 'views/head_css.php';
        ?>
        <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
    </head>
    <body class="<?= htmlspecialchars($bodyClass, ENT_QUOTES) ?>">
        <?php include PROJECT_ROOT . 'views/header.php'; ?>
        <?php include PROJECT_ROOT . 'dashboard/views/sidebar.php'; ?>

        <main class="dashboard-content">
            <section class="progress-locked">
                <div class="progress-locked__icon"><i class="fas fa-lock"></i></div>
                <span class="progress-kicker"><i class="fas fa-bolt"></i> <?= t('progress.locked.kicker') ?></span>
                <h1><?= t('progress.locked.title') ?></h1>
                <p><?= t('progress.locked.body') ?></p>
                <div class="progress-locked__actions">
                    <a href="<?= BASE_URL ?>login.php" class="progress-button progress-button--primary"><?= t('progress.locked.sign_in') ?></a>
                    <a href="<?= BASE_URL ?>signup.php" class="progress-button progress-button--ghost"><?= t('progress.locked.create') ?></a>
                </div>
            </section>
        </main>

        <?php include PROJECT_ROOT . 'views/footer.php'; ?>
    </body>
    </html>
    <?php
    exit;
}

require_once __DIR__ . '/../include/handlers/achievements.php';
require_once __DIR__ . '/../include/handlers/log_attempt.php';

log_attempt($pdo, (int) $user['user_id'], 'view', 'User ' . $user['user_id'] . ' opened Progress page', 'dashboard', null);

$progressData = bb_achievements_progress($pdo, (int) $user['user_id']);
$summary = $progressData['summary'];
$xp = $summary['xp'];
$achievements = $progressData['achievements'];
$records = $progressData['records'];
$nextLevel = (int) $xp['current_level'] + 1;
$achievementPct = $summary['total_achievements'] > 0
    ? (int) round($summary['unlocked'] / $summary['total_achievements'] * 100)
    : 0;

function bb_progress_format_value($value): string
{
    return is_numeric($value) ? number_format((float) $value) : (string) $value;
}

/**
 * Translate a raw achievement/record unit string (e.g. "meals", "XP") via the
 * i18n layer. Falls back to the raw value for unknown units.
 */
function bb_progress_unit(string $unit): string
{
    static $map = [
        // Achievement units (from bb_achievement_build in achievements.php)
        'food logged'      => 'progress.unit.food_logged',
        'logged days'      => 'progress.unit.logged_days',
        'best streak days' => 'progress.unit.best_streak_days',
        'full days'        => 'progress.unit.full_days',
        'balanced days'    => 'progress.unit.balanced_days',
        'total XP'         => 'progress.unit.total_xp',
        'rice logs'        => 'progress.unit.rice_logs',
        'pho logs'         => 'progress.unit.pho_logs',
        'banh mi logs'     => 'progress.unit.banh_mi_logs',
        'friends'          => 'progress.unit.friends',
        'rank 1 status'    => 'progress.unit.rank1',
        'comebacks'        => 'progress.unit.comebacks',
        // Record units
        'days'             => 'progress.unit.days',
        'XP'               => 'progress.unit.xp',
        'foods'            => 'progress.unit.foods',
    ];
    return isset($map[$unit]) ? t_raw($map[$unit]) : $unit;
}

function bb_progress_render_achievement(array $achievement): void
{
    $level = (int) $achievement['level'];
    $maxLevel = (int) $achievement['max_level'];
    $isUnlocked = $level > 0;
    $isComplete = !empty($achievement['is_complete']);
    $cardClass = 'achievement-card achievement-card--' . htmlspecialchars($achievement['tone'], ENT_QUOTES);
    if (!$isUnlocked) $cardClass .= ' achievement-card--locked';
    if ($isComplete) $cardClass .= ' achievement-card--complete';
    $levelText = $isComplete
        ? t_raw('progress.ach.max_level')
        : ($isUnlocked ? t_raw('progress.ach.level_x_of_y', ['n' => $level, 'm' => $maxLevel]) : t_raw('progress.ach.locked'));

    // Translate name/description by the achievement `id`; fall back to the
    // English value carried in the data if a key is somehow missing.
    $achKey = $achievement['id'] ?? '';
    $achName = $achKey ? t_raw('progress.ach.' . $achKey . '.name') : $achievement['name'];
    if ($achName === 'progress.ach.' . $achKey . '.name') $achName = $achievement['name'];
    $achDesc = $achKey ? t_raw('progress.ach.' . $achKey . '.desc') : $achievement['description'];
    if ($achDesc === 'progress.ach.' . $achKey . '.desc') $achDesc = $achievement['description'];
    $achUnit = bb_progress_unit((string) $achievement['unit']);
    ?>
    <article class="<?= $cardClass ?>">
        <div class="achievement-card__top">
            <div class="achievement-card__icon">
                <i class="fas <?= htmlspecialchars($achievement['icon'], ENT_QUOTES) ?>"></i>
            </div>
            <span class="achievement-card__level"><?= htmlspecialchars($levelText, ENT_QUOTES) ?></span>
        </div>
        <h3><?= htmlspecialchars($achName, ENT_QUOTES) ?></h3>
        <p><?= htmlspecialchars($achDesc, ENT_QUOTES) ?></p>
        <div class="achievement-card__progress">
            <div class="achievement-card__numbers">
                <span><?= number_format((int) $achievement['value']) ?> <?= htmlspecialchars($achUnit, ENT_QUOTES) ?></span>
                <strong><?= number_format((int) $achievement['next_target']) ?></strong>
            </div>
            <progress value="<?= (int) $achievement['progress_pct'] ?>" max="100"></progress>
        </div>
    </article>
    <?php
}
?>
<!DOCTYPE html>
<html lang="<?= html_lang_attr() ?>" data-theme="<?= htmlspecialchars($_SESSION['user']['theme_preference'] ?? 'system', ENT_QUOTES) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('progress.title_alt') ?></title>
    <?php
    $pageComponents = ['sidebar', 'fab'];
    $pageCss = ['css/dashboard.css', 'css/pages/dashboard-progress.css', 'css/components/story-share.css'];
    include PROJECT_ROOT . 'views/head_css.php';
    ?>
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
</head>

<body class="<?= htmlspecialchars($bodyClass, ENT_QUOTES) ?>">
    <?php include PROJECT_ROOT . 'views/header.php'; ?>
    <?php include PROJECT_ROOT . 'dashboard/views/sidebar.php'; ?>

    <main class="dashboard-content">
        <div class="progress-container">
            <section class="progress-hero">
                <div class="progress-hero__copy">
                    <span class="progress-kicker"><i class="fas fa-bolt"></i> <?= t('progress.hero.kicker') ?></span>
                    <h1><?= t('progress.hero.level_title', ['n' => (int) $xp['current_level']]) ?></h1>
                    <p><?= t('progress.hero.subtitle') ?></p>
                    <button id="btnOpenStory" class="story-btn-primary">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> <?= t('progress.hero.weekly_wrapped') ?>
                    </button>
                </div>
                <div class="progress-level-card">
                    <div class="progress-level-card__ring">
                        <strong><?= (int) $xp['progress_pct'] ?>%</strong>
                        <span><?= t('progress.level.to_next', ['n' => $nextLevel]) ?></span>
                    </div>
                    <div class="progress-level-card__body">
                        <span><?= number_format((int) $xp['xp_into_level']) ?> / <?= number_format((int) $xp['xp_for_next']) ?> XP</span>
                        <progress value="<?= (int) $xp['progress_pct'] ?>" max="100"></progress>
                    </div>
                </div>
            </section>

            <section class="progress-stat-grid">
                <div class="progress-stat">
                    <span><?= t('progress.stat.total_xp') ?></span>
                    <strong><?= number_format((int) $xp['total_xp']) ?></strong>
                </div>
                <div class="progress-stat">
                    <span><?= t('progress.stat.achievements') ?></span>
                    <strong><?= (int) $summary['unlocked'] ?>/<?= (int) $summary['total_achievements'] ?></strong>
                </div>
                <div class="progress-stat">
                    <span><?= t('progress.stat.current_streak') ?></span>
                    <strong><?= (int) $summary['current_streak'] ?><?= t('progress.streak.day_short') ?></strong>
                </div>
                <div class="progress-stat">
                    <span><?= t('progress.stat.foods_logged') ?></span>
                    <strong><?= number_format((int) $summary['total_foods']) ?></strong>
                </div>
            </section>

            <section class="progress-section">
                <div class="progress-section__header">
                    <div>
                        <span class="progress-kicker"><i class="fas fa-medal"></i> <?= t('progress.section.awards') ?></span>
                        <h2><?= t('progress.section.achievements') ?></h2>
                    </div>
                    <div class="progress-section__meter">
                        <span><?= t('progress.section.pct_unlocked', ['n' => $achievementPct]) ?></span>
                        <progress value="<?= $achievementPct ?>" max="100"></progress>
                    </div>
                </div>

                <div class="achievement-grid">
                    <?php foreach ($achievements as $achievement) bb_progress_render_achievement($achievement); ?>
                </div>
            </section>

            <section class="progress-section progress-section--records">
                <div class="progress-section__header">
                    <div>
                        <span class="progress-kicker"><i class="fas fa-chart-line"></i> <?= t('progress.section.records_kicker') ?></span>
                        <h2><?= t('progress.section.records_title') ?></h2>
                    </div>
                </div>

                <div class="record-grid">
                    <?php foreach ($records as $record): ?>
                        <?php
                        // Translate the record label by its key; fall back to the
                        // English label carried in the data.
                        $recKey = $record['key'] ?? '';
                        $recLabel = $recKey ? t_raw('progress.rec.' . $recKey) : $record['label'];
                        if ($recLabel === 'progress.rec.' . $recKey) $recLabel = $record['label'];
                        ?>
                        <article class="record-card">
                            <div class="record-card__icon"><i class="fas <?= htmlspecialchars($record['icon'], ENT_QUOTES) ?>"></i></div>
                            <div>
                                <span><?= htmlspecialchars($recLabel, ENT_QUOTES) ?></span>
                                <strong><?= htmlspecialchars(bb_progress_format_value($record['value']), ENT_QUOTES) ?></strong>
                                <?php if ($record['unit'] !== ''): ?>
                                    <small><?= htmlspecialchars(bb_progress_unit((string) $record['unit']), ENT_QUOTES) ?></small>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
    </main>

    <?php include PROJECT_ROOT . 'views/footer.php'; ?>
    <script src="<?= BASE_URL ?>js/story-share.js" defer></script>
</body>
</html>
