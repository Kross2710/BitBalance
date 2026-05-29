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
    <html lang="en" data-theme="<?= htmlspecialchars($_SESSION['user']['theme_preference'] ?? 'system', ENT_QUOTES) ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Progress | BitBalance</title>
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
                <span class="progress-kicker"><i class="fas fa-bolt"></i> XP vault</span>
                <h1>Sign in to view your progress</h1>
                <p>Your XP, records, and food achievements live behind your BitBalance account.</p>
                <div class="progress-locked__actions">
                    <a href="<?= BASE_URL ?>login.php" class="progress-button progress-button--primary">Sign in</a>
                    <a href="<?= BASE_URL ?>signup.php" class="progress-button progress-button--ghost">Create account</a>
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

function bb_progress_render_achievement(array $achievement): void
{
    $level = (int) $achievement['level'];
    $maxLevel = (int) $achievement['max_level'];
    $isUnlocked = $level > 0;
    $isComplete = !empty($achievement['is_complete']);
    $cardClass = 'achievement-card achievement-card--' . htmlspecialchars($achievement['tone'], ENT_QUOTES);
    if (!$isUnlocked) $cardClass .= ' achievement-card--locked';
    if ($isComplete) $cardClass .= ' achievement-card--complete';
    $levelText = $isComplete ? 'Max level' : ($isUnlocked ? 'Level ' . $level . '/' . $maxLevel : 'Locked');
    ?>
    <article class="<?= $cardClass ?>">
        <div class="achievement-card__top">
            <div class="achievement-card__icon">
                <i class="fas <?= htmlspecialchars($achievement['icon'], ENT_QUOTES) ?>"></i>
            </div>
            <span class="achievement-card__level"><?= htmlspecialchars($levelText, ENT_QUOTES) ?></span>
        </div>
        <h3><?= htmlspecialchars($achievement['name'], ENT_QUOTES) ?></h3>
        <p><?= htmlspecialchars($achievement['description'], ENT_QUOTES) ?></p>
        <div class="achievement-card__progress">
            <div class="achievement-card__numbers">
                <span><?= number_format((int) $achievement['value']) ?> <?= htmlspecialchars($achievement['unit'], ENT_QUOTES) ?></span>
                <strong><?= number_format((int) $achievement['next_target']) ?></strong>
            </div>
            <progress value="<?= (int) $achievement['progress_pct'] ?>" max="100"></progress>
        </div>
    </article>
    <?php
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($_SESSION['user']['theme_preference'] ?? 'system', ENT_QUOTES) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progress | BitBalance</title>
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
                    <span class="progress-kicker"><i class="fas fa-bolt"></i> Progress lab</span>
                    <h1>Level <?= (int) $xp['current_level'] ?></h1>
                    <p>Track XP, personal records, and suspiciously specific food achievements.</p>
                    <button id="btnOpenStory" class="story-btn-primary" style="margin-top: 16px;">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> Weekly Wrapped ✨
                    </button>
                </div>
                <div class="progress-level-card">
                    <div class="progress-level-card__ring">
                        <strong><?= (int) $xp['progress_pct'] ?>%</strong>
                        <span>to Lv <?= $nextLevel ?></span>
                    </div>
                    <div class="progress-level-card__body">
                        <span><?= number_format((int) $xp['xp_into_level']) ?> / <?= number_format((int) $xp['xp_for_next']) ?> XP</span>
                        <progress value="<?= (int) $xp['progress_pct'] ?>" max="100"></progress>
                    </div>
                </div>
            </section>

            <section class="progress-stat-grid">
                <div class="progress-stat">
                    <span>Total XP</span>
                    <strong><?= number_format((int) $xp['total_xp']) ?></strong>
                </div>
                <div class="progress-stat">
                    <span>Achievements</span>
                    <strong><?= (int) $summary['unlocked'] ?>/<?= (int) $summary['total_achievements'] ?></strong>
                </div>
                <div class="progress-stat">
                    <span>Current streak</span>
                    <strong><?= (int) $summary['current_streak'] ?>d</strong>
                </div>
                <div class="progress-stat">
                    <span>Foods logged</span>
                    <strong><?= number_format((int) $summary['total_foods']) ?></strong>
                </div>
            </section>

            <section class="progress-section">
                <div class="progress-section__header">
                    <div>
                        <span class="progress-kicker"><i class="fas fa-medal"></i> Awards</span>
                        <h2>Achievements</h2>
                    </div>
                    <div class="progress-section__meter">
                        <span><?= $achievementPct ?>% unlocked</span>
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
                        <span class="progress-kicker"><i class="fas fa-chart-line"></i> Personal records</span>
                        <h2>Your best bits</h2>
                    </div>
                </div>

                <div class="record-grid">
                    <?php foreach ($records as $record): ?>
                        <article class="record-card">
                            <div class="record-card__icon"><i class="fas <?= htmlspecialchars($record['icon'], ENT_QUOTES) ?>"></i></div>
                            <div>
                                <span><?= htmlspecialchars($record['label'], ENT_QUOTES) ?></span>
                                <strong><?= htmlspecialchars(bb_progress_format_value($record['value']), ENT_QUOTES) ?></strong>
                                <?php if ($record['unit'] !== ''): ?>
                                    <small><?= htmlspecialchars((string) $record['unit'], ENT_QUOTES) ?></small>
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
