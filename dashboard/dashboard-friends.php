<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../include/init.php';
require_once __DIR__ . '/../include/csrf.php';
require_once __DIR__ . '/../include/handlers/log_attempt.php';

$activePage   = 'friends';
$activeHeader = 'dashboard';
$bodyClass    = 'page-friends';
$displayUser  = $isLoggedIn ? $user['user_name'] : 'Guest';

if (!$isLoggedIn) {
    ?>
    <!DOCTYPE html>
    <html lang="<?= html_lang_attr() ?>"
        data-theme="<?php echo isset($_SESSION['user']) ? ($_SESSION['user']['theme_preference'] ?? 'system') : 'system'; ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= t('friends.title_alt') ?></title>
        <?php
        $pageComponents = ['sidebar'];
        $pageCss = ['css/dashboard.css', 'css/pages/dashboard-friends.css'];
        include PROJECT_ROOT . 'views/head_css.php';
        ?>
        <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
    </head>

    <body class="<?= htmlspecialchars($bodyClass, ENT_QUOTES) ?>">
        <?php include PROJECT_ROOT . 'views/header.php'; ?>
        <?php include PROJECT_ROOT . 'dashboard/views/sidebar.php'; ?>

        <main class="dashboard-content">
            <section class="friends-locked">
                <div class="friends-locked__icon"><i class="fas fa-user-lock"></i></div>
                <span class="friends-kicker"><i class="fas fa-user-friends"></i> <?= t('friends.locked.kicker') ?></span>
                <h1><?= t('friends.locked.title') ?></h1>
                <p><?= t('friends.locked.body') ?></p>
                <div class="friends-locked__actions">
                    <a href="<?= BASE_URL ?>login.php" class="btn-tactile btn-tactile--primary"><?= t('friends.locked.sign_in') ?></a>
                    <a href="<?= BASE_URL ?>signup.php" class="btn-tactile btn-tactile--ghost"><?= t('friends.locked.create') ?></a>
                </div>
            </section>
        </main>

        <?php include PROJECT_ROOT . 'views/footer.php'; ?>
    </body>
    </html>
    <?php
    exit;
}

require_once __DIR__ . '/../include/handlers/friends.php';

log_attempt($pdo, $user['user_id'], 'view', 'User ' . $user['user_id'] . ' opened Friends page', 'dashboard', null);

$me = (int) $user['user_id'];

// Initial server-side render so the page works without JS (degrades to fully
// usable lists; only the search input needs JS).
$friends     = friends_list($pdo, $me);
$pendingIn   = friends_pending_incoming($pdo, $me);
$pendingOut  = friends_pending_outgoing($pdo, $me);
$pendingTotal = count($pendingIn) + count($pendingOut);
$leaderboardPeriod = ($_GET['period'] ?? 'weekly') === 'all_time' ? 'all_time' : 'weekly';
$leaderboardRows = leaderboard_friends($pdo, $me, $leaderboardPeriod, 500);

$activeTabFromUrl = $_GET['tab'] ?? 'friends';
if (!in_array($activeTabFromUrl, ['friends', 'pending', 'leaderboard', 'find'], true)) {
    $activeTabFromUrl = 'friends';
}

$csrfToken    = csrf_token();

/**
 * Render a friend / search-result card. Keeps markup DRY across tabs.
 */
function fr_render_card(array $u, string $context, string $csrf): void
{
    $name   = htmlspecialchars($u['user_name'] ?? '', ENT_QUOTES);
    $avatar = $u['profile_image'] ?? '';
    $level  = (int) ($u['current_level'] ?? 1);
    $streak = (int) ($u['logging_streak'] ?? 0);
    $weekly = isset($u['weekly_xp']) ? (int) $u['weekly_xp'] : null;
    $uid    = (int) ($u['user_id'] ?? 0);
    $reqId  = (int) ($u['request_id'] ?? 0);
    $rel    = $u['relationship'] ?? null;
    ?>
    <article class="friend-card" data-user-id="<?= $uid ?>" data-request-id="<?= $reqId ?>">
        <div class="friend-card__avatar">
            <?php if ($avatar): ?>
                <img src="<?= BASE_URL . htmlspecialchars($avatar, ENT_QUOTES) ?>" alt="">
            <?php else: ?>
                <i class="fas fa-user"></i>
            <?php endif; ?>
            <span class="friend-card__level">Lv <?= $level ?></span>
        </div>
        <div class="friend-card__body">
            <h3 class="friend-card__name"><?= $name ?></h3>
            <div class="friend-card__stats">
                <?php if ($streak > 0): ?>
                    <span class="friend-card__stat"><i class="fas fa-fire"></i> <?= $streak ?><?= t('friends.card.day_short') ?></span>
                <?php endif; ?>
                <?php if ($weekly !== null): ?>
                    <span class="friend-card__stat"><i class="fas fa-bolt"></i> <?= t('friends.card.weekly_xp', ['n' => number_format($weekly)]) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="friend-card__actions">
            <?php if ($context === 'friend'): ?>
                <button class="btn-tactile btn-tactile--ghost js-unfriend" data-csrf="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
                    <i class="fas fa-user-minus"></i> <?= t('friends.card.btn_remove') ?>
                </button>
            <?php elseif ($context === 'pending_in'): ?>
                <button class="btn-tactile btn-tactile--primary js-accept" data-csrf="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
                    <i class="fas fa-check"></i> <?= t('friends.card.btn_accept') ?>
                </button>
                <button class="btn-tactile btn-tactile--ghost js-reject" data-csrf="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
                    <?= t('friends.card.btn_decline') ?>
                </button>
            <?php elseif ($context === 'pending_out'): ?>
                <span class="friend-card__hint"><?= t('friends.card.hint_waiting') ?></span>
                <button class="btn-tactile btn-tactile--ghost js-cancel" data-csrf="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
                    <?= t('friends.card.btn_cancel') ?>
                </button>
            <?php elseif ($context === 'find'): ?>
                <?php if ($rel === 'friends'): ?>
                    <span class="friend-card__hint friend-card__hint--ok"><i class="fas fa-check"></i> <?= t('friends.card.hint_friends') ?></span>
                <?php elseif ($rel === 'pending_out'): ?>
                    <span class="friend-card__hint"><?= t('friends.card.hint_pending') ?></span>
                <?php elseif ($rel === 'pending_in'): ?>
                    <button class="btn-tactile btn-tactile--primary js-accept-by-user" data-csrf="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
                        <?= t('friends.card.btn_accept') ?>
                    </button>
                <?php else: ?>
                    <button class="btn-tactile btn-tactile--primary js-send" data-csrf="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
                        <i class="fas fa-user-plus"></i> <?= t('friends.card.btn_add') ?>
                    </button>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </article>
    <?php
}

/**
 * Render a leaderboard row for the Friends leaderboard tab.
 */
function fr_render_leaderboard_row(array $row, string $period): void
{
    $rank = (int) ($row['rank'] ?? 0);
    $rankClass = $rank === 1 ? ' leaderboard-rank--gold' : ($rank === 2 ? ' leaderboard-rank--silver' : ($rank === 3 ? ' leaderboard-rank--bronze' : ''));
    $name = htmlspecialchars($row['user_name'] ?? '', ENT_QUOTES);
    $avatar = $row['profile_image'] ?? '';
    $level = (int) ($row['current_level'] ?? 1);
    $streak = (int) ($row['logging_streak'] ?? 0);
    $score = (int) ($row['score_xp'] ?? 0);
    $weekly = (int) ($row['weekly_xp'] ?? 0);
    $total = (int) ($row['total_xp'] ?? 0);
    $isYou = !empty($row['is_current_user']);
    $scoreLabel = $period === 'all_time' ? t_raw('friends.lb.score.total_xp') : t_raw('friends.lb.score.weekly');
    $secondaryScore = $period === 'all_time'
        ? t_raw('friends.lb.secondary.weekly', ['n' => number_format($weekly)])
        : t_raw('friends.lb.secondary.total', ['n' => number_format($total)]);
    ?>
    <article class="leaderboard-row<?= $isYou ? ' leaderboard-row--you' : '' ?>" data-user-id="<?= (int) ($row['user_id'] ?? 0) ?>">
        <div class="leaderboard-rank<?= $rankClass ?>">
            <?php if ($rank <= 3): ?>
                <i class="fas <?= $rank === 1 ? 'fa-trophy' : 'fa-medal' ?>"></i>
            <?php else: ?>
                <?= $rank ?>
            <?php endif; ?>
        </div>
        <div class="leaderboard-avatar">
            <?php if ($avatar): ?>
                <img src="<?= BASE_URL . htmlspecialchars($avatar, ENT_QUOTES) ?>" alt="">
            <?php else: ?>
                <i class="fas fa-user"></i>
            <?php endif; ?>
        </div>
        <div class="leaderboard-main">
            <div class="leaderboard-name-line">
                <h3><?= $name ?></h3>
                <?php if ($isYou): ?><span class="leaderboard-you-chip"><?= t('friends.lb.you') ?></span><?php endif; ?>
            </div>
            <div class="leaderboard-meta">
                <span><i class="fas fa-shield-alt"></i> Lv <?= $level ?></span>
                <span><i class="fas fa-fire"></i> <?= t_raw('friends.lb.streak', ['n' => $streak, 'day_short' => t_raw('friends.card.day_short')]) ?></span>
                <span><i class="fas fa-bolt"></i> <?= htmlspecialchars($secondaryScore) ?></span>
            </div>
        </div>
        <div class="leaderboard-score">
            <strong><?= number_format($score) ?></strong>
            <span><?= htmlspecialchars($scoreLabel) ?></span>
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
    <title><?= t('friends.title_alt') ?></title>
    <?php
    $pageComponents = ['sidebar', 'fab'];
    $pageCss = ['css/dashboard.css', 'css/pages/dashboard-friends.css'];
    include PROJECT_ROOT . 'views/head_css.php';
    ?>
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
</head>

<body class="<?= htmlspecialchars($bodyClass, ENT_QUOTES) ?>">
    <?php include PROJECT_ROOT . 'views/header.php'; ?>
    <?php include PROJECT_ROOT . 'dashboard/views/sidebar.php'; ?>

    <main class="dashboard-content">
        <div class="friends-container" data-active-tab="<?= htmlspecialchars($activeTabFromUrl, ENT_QUOTES) ?>">
            <section class="friends-hero">
                <div class="friends-hero__copy">
                    <span class="friends-kicker"><i class="fas fa-user-friends"></i> <?= t('friends.hero.kicker') ?></span>
                    <h1><?= t('friends.hero.title') ?></h1>
                    <p><?= t('friends.hero.subtitle') ?></p>
                </div>
                <div class="friends-hero__metrics">
                    <div class="friends-metric">
                        <span class="friends-metric__label"><?= t('friends.metric.friends') ?></span>
                        <strong><?= count($friends) ?></strong>
                    </div>
                    <div class="friends-metric">
                        <span class="friends-metric__label"><?= t('friends.metric.pending') ?></span>
                        <strong><?= $pendingTotal ?></strong>
                    </div>
                </div>
            </section>

            <nav class="friends-tabs" role="tablist">
                <button class="friends-tab" role="tab" data-tab="friends" aria-selected="<?= $activeTabFromUrl === 'friends' ? 'true' : 'false' ?>">
                    <?= t('friends.tab.my') ?>
                    <span class="friends-tab__badge friends-tab__badge--muted"><?= count($friends) ?></span>
                </button>
                <button class="friends-tab" role="tab" data-tab="pending" aria-selected="<?= $activeTabFromUrl === 'pending' ? 'true' : 'false' ?>">
                    <?= t('friends.tab.pending') ?>
                    <?php if ($pendingTotal > 0): ?>
                        <span class="friends-tab__badge friends-tab__badge--alert"><?= $pendingTotal ?></span>
                    <?php endif; ?>
                </button>
                <button class="friends-tab" role="tab" data-tab="leaderboard" aria-selected="<?= $activeTabFromUrl === 'leaderboard' ? 'true' : 'false' ?>">
                    <?= t('friends.tab.leaderboard') ?>
                    <span class="friends-tab__badge friends-tab__badge--muted"><?= count($leaderboardRows) ?></span>
                </button>
                <button class="friends-tab" role="tab" data-tab="find" aria-selected="<?= $activeTabFromUrl === 'find' ? 'true' : 'false' ?>">
                    <?= t('friends.tab.find') ?>
                </button>
            </nav>

            <!-- ============================== My Friends ============================== -->
            <section class="friends-panel" data-panel="friends" <?= $activeTabFromUrl === 'friends' ? '' : 'hidden' ?>>
                <?php if (empty($friends)): ?>
                    <div class="friends-empty">
                        <div class="friends-empty__icon"><i class="fas fa-users-slash"></i></div>
                        <h3><?= t('friends.empty.no_friends_title') ?></h3>
                        <p><?= t_raw('friends.empty.no_friends_body', ['link' => '<button class="link-button" data-jump-tab="find">' . htmlspecialchars(t_raw('friends.tab.find')) . '</button>']) ?></p>
                    </div>
                <?php else: ?>
                    <div class="friends-grid" id="friendsGrid">
                        <?php foreach ($friends as $f) fr_render_card($f, 'friend', $csrfToken); ?>
                    </div>
                <?php endif; ?>
            </section>

            <!-- =============================== Pending ================================ -->
            <section class="friends-panel" data-panel="pending" <?= $activeTabFromUrl === 'pending' ? '' : 'hidden' ?>>
                <div class="friends-subhead"><?= t('friends.subhead.requests_in') ?></div>
                <?php if (empty($pendingIn)): ?>
                    <div class="friends-empty friends-empty--inline">
                        <p><?= t('friends.empty.no_incoming') ?></p>
                    </div>
                <?php else: ?>
                    <div class="friends-grid" id="pendingInGrid">
                        <?php foreach ($pendingIn as $p) fr_render_card($p, 'pending_in', $csrfToken); ?>
                    </div>
                <?php endif; ?>

                <div class="friends-subhead friends-subhead--spaced"><?= t('friends.subhead.requests_out') ?></div>
                <?php if (empty($pendingOut)): ?>
                    <div class="friends-empty friends-empty--inline">
                        <p><?= t('friends.empty.no_outgoing') ?></p>
                    </div>
                <?php else: ?>
                    <div class="friends-grid" id="pendingOutGrid">
                        <?php foreach ($pendingOut as $p) fr_render_card($p, 'pending_out', $csrfToken); ?>
                    </div>
                <?php endif; ?>
            </section>

            <!-- ============================== Leaderboard ============================= -->
            <section class="friends-panel" data-panel="leaderboard" <?= $activeTabFromUrl === 'leaderboard' ? '' : 'hidden' ?>>
                <div class="leaderboard-panel-head">
                    <div>
                        <div class="friends-subhead"><?= t('friends.lb.subhead') ?></div>
                        <p class="leaderboard-copy"><?= t('friends.lb.copy') ?></p>
                    </div>
                    <div class="leaderboard-period-toggle" role="group" aria-label="<?= t('friends.lb.period_label') ?>">
                        <button
                            type="button"
                            class="leaderboard-period"
                            data-period="weekly"
                            aria-pressed="<?= $leaderboardPeriod === 'weekly' ? 'true' : 'false' ?>">
                            <?= t('friends.lb.weekly') ?>
                        </button>
                        <button
                            type="button"
                            class="leaderboard-period"
                            data-period="all_time"
                            aria-pressed="<?= $leaderboardPeriod === 'all_time' ? 'true' : 'false' ?>">
                            <?= t('friends.lb.all_time') ?>
                        </button>
                    </div>
                </div>

                <div class="leaderboard-list" id="leaderboardList" data-period="<?= htmlspecialchars($leaderboardPeriod, ENT_QUOTES) ?>">
                    <?php if (empty($leaderboardRows)): ?>
                        <div class="friends-empty friends-empty--inline">
                            <p><?= t('friends.lb.empty') ?></p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($leaderboardRows as $row) fr_render_leaderboard_row($row, $leaderboardPeriod); ?>
                    <?php endif; ?>
                </div>

                <div class="friends-empty friends-empty--inline leaderboard-solo-state" <?= count($leaderboardRows) <= 1 ? '' : 'hidden' ?>>
                    <h3><?= t('friends.lb.solo_title') ?></h3>
                    <p><?= t('friends.lb.solo_body') ?></p>
                    <button class="btn-tactile btn-tactile--primary" type="button" data-jump-tab="find">
                        <i class="fas fa-user-plus"></i> <?= t('friends.tab.find') ?>
                    </button>
                </div>
            </section>

            <!-- =============================== Find People ============================ -->
            <section class="friends-panel" data-panel="find" <?= $activeTabFromUrl === 'find' ? '' : 'hidden' ?>>
                <div class="friends-search">
                    <i class="fas fa-search friends-search__icon"></i>
                    <input
                        type="search"
                        id="friendsSearchInput"
                        class="friends-search__input"
                        placeholder="<?= t('friends.find.placeholder') ?>"
                        autocomplete="off"
                        spellcheck="false">
                </div>
                <div class="friends-grid" id="searchResultsGrid">
                    <div class="friends-empty friends-empty--inline" id="searchHint">
                        <p><?= t('friends.find.start_typing') ?></p>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <?php include PROJECT_ROOT . 'views/footer.php'; ?>

    <script>
        (function () {
            const CSRF = <?= json_encode($csrfToken) ?>;
            const ENDPOINT = '<?= BASE_URL ?>dashboard/handlers/friends_action.php';
            const I18N = {
                you: <?= json_encode(t_raw('friends.lb.you')) ?>,
                pending: <?= json_encode(t_raw('friends.card.hint_pending')) ?>,
                friends: <?= json_encode(t_raw('friends.card.hint_friends')) ?>,
                streakSuffix: <?= json_encode(t_raw('friends.card.day_short')) ?>,
                scoreTotal: <?= json_encode(t_raw('friends.lb.score.total_xp')) ?>,
                scoreWeekly: <?= json_encode(t_raw('friends.lb.score.weekly')) ?>,
                secondaryWeekly: <?= json_encode(t_raw('friends.lb.secondary.weekly', ['n' => '{n}'])) ?>,
                secondaryTotal: <?= json_encode(t_raw('friends.lb.secondary.total', ['n' => '{n}'])) ?>,
                lbLoading: <?= json_encode(t_raw('friends.lb.loading')) ?>,
                lbFailed: <?= json_encode(t_raw('friends.lb.failed', ['error' => '{error}'])) ?>,
                startTyping: <?= json_encode(t_raw('friends.find.start_typing')) ?>,
                searching: <?= json_encode(t_raw('friends.find.searching')) ?>,
            };
            const container = document.querySelector('.friends-container');
            const leaderboardList = document.getElementById('leaderboardList');
            const leaderboardSoloState = container.querySelector('.leaderboard-solo-state');
            const leaderboardPeriodButtons = container.querySelectorAll('.leaderboard-period');

            // -------- Tab switching --------
            const tabs = container.querySelectorAll('.friends-tab');
            const panels = container.querySelectorAll('.friends-panel');
            function setTab(name) {
                tabs.forEach(t => t.setAttribute('aria-selected', t.dataset.tab === name ? 'true' : 'false'));
                panels.forEach(p => p.toggleAttribute('hidden', p.dataset.panel !== name));
                container.dataset.activeTab = name;
                const url = new URL(window.location.href);
                url.searchParams.set('tab', name);
                history.replaceState({}, '', url.toString());
                if (name === 'leaderboard' && leaderboardList) {
                    loadLeaderboard(leaderboardList.dataset.period || 'weekly');
                }
            }
            tabs.forEach(t => t.addEventListener('click', () => setTab(t.dataset.tab)));
            container.addEventListener('click', e => {
                const jump = e.target.closest('[data-jump-tab]');
                if (jump) setTab(jump.dataset.jumpTab);
            });

            // -------- Action helpers --------
            const MUTATIONS = new Set(['send', 'accept', 'reject', 'cancel', 'unfriend']);
            async function postAction(action, payload) {
                const fd = new FormData();
                fd.append('action', action);
                fd.append('csrf_token', CSRF);
                Object.entries(payload || {}).forEach(([k, v]) => fd.append(k, v));
                const res = await fetch(ENDPOINT, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'fetch' },
                    body: fd
                });
                const data = await res.json().catch(() => ({ ok: false, error: 'Bad response' }));
                if (!data.ok) throw new Error(data.error || 'Action failed');
                // After my own mutation, reconcile my view quickly (the other side
                // catches up on its next poll tick).
                if (MUTATIONS.has(action)) setTimeout(() => pollState(), 250);
                return data;
            }

            function findCard(el) { return el.closest('.friend-card'); }
            function flashError(msg) { showToast(msg, { type: 'error' }); } // simple for MVP

            // -------- Leaderboard period toggle --------
            function escapeHtml(value) {
                return String(value || '').replace(/[&<>"']/g, c => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                }[c]));
            }

            function toNumber(value) {
                const n = parseInt(value || 0, 10);
                return Number.isNaN(n) ? 0 : n;
            }

            function formatNumber(value) {
                return toNumber(value).toLocaleString();
            }

            function leaderboardRankClass(rank) {
                if (rank === 1) return ' leaderboard-rank--gold';
                if (rank === 2) return ' leaderboard-rank--silver';
                if (rank === 3) return ' leaderboard-rank--bronze';
                return '';
            }

            function renderLeaderboardRow(u, period) {
                const rank = toNumber(u.rank);
                const isYou = u.is_current_user === true || u.is_current_user === 1 || u.is_current_user === '1';
                const avatar = u.profile_image
                    ? '<img src="<?= BASE_URL ?>' + escapeHtml(u.profile_image) + '" alt="">'
                    : '<i class="fas fa-user"></i>';
                const rankContent = rank <= 3
                    ? '<i class="fas ' + (rank === 1 ? 'fa-trophy' : 'fa-medal') + '"></i>'
                    : String(rank);
                const scoreLabel = period === 'all_time' ? I18N.scoreTotal : I18N.scoreWeekly;
                const secondaryScore = period === 'all_time'
                    ? I18N.secondaryWeekly.replace('{n}', formatNumber(u.weekly_xp))
                    : I18N.secondaryTotal.replace('{n}', formatNumber(u.total_xp));

                return `
                    <article class="leaderboard-row${isYou ? ' leaderboard-row--you' : ''}" data-user-id="${toNumber(u.user_id)}">
                        <div class="leaderboard-rank${leaderboardRankClass(rank)}">${rankContent}</div>
                        <div class="leaderboard-avatar">${avatar}</div>
                        <div class="leaderboard-main">
                            <div class="leaderboard-name-line">
                                <h3>${escapeHtml(u.user_name)}</h3>
                                ${isYou ? '<span class="leaderboard-you-chip">' + escapeHtml(I18N.you) + '</span>' : ''}
                            </div>
                            <div class="leaderboard-meta">
                                <span><i class="fas fa-shield-alt"></i> Lv ${formatNumber(u.current_level)}</span>
                                <span><i class="fas fa-fire"></i> ${formatNumber(u.logging_streak)}${I18N.streakSuffix} streak</span>
                                <span><i class="fas fa-bolt"></i> ${secondaryScore}</span>
                            </div>
                        </div>
                        <div class="leaderboard-score">
                            <strong>${formatNumber(u.score_xp)}</strong>
                            <span>${scoreLabel}</span>
                        </div>
                    </article>`;
            }

            async function loadLeaderboard(period) {
                if (!leaderboardList) return;
                leaderboardPeriodButtons.forEach(btn => {
                    btn.setAttribute('aria-pressed', btn.dataset.period === period ? 'true' : 'false');
                    btn.disabled = true;
                });
                leaderboardList.dataset.period = period;
                leaderboardList.innerHTML = '<div class="friends-empty friends-empty--inline"><p>' + escapeHtml(I18N.lbLoading) + '</p></div>';
                try {
                    const data = await postAction('leaderboard', { period: period, limit: 500 });
                    const rows = data.leaders || [];
                    leaderboardList.innerHTML = rows.length
                        ? rows.map(row => renderLeaderboardRow(row, data.period || period)).join('')
                        : '<div class="friends-empty friends-empty--inline"><p>No leaderboard data yet.</p></div>';
                    if (leaderboardSoloState) {
                        leaderboardSoloState.toggleAttribute('hidden', rows.length > 1);
                    }
                    const url = new URL(window.location.href);
                    url.searchParams.set('tab', 'leaderboard');
                    url.searchParams.set('period', data.period || period);
                    history.replaceState({}, '', url.toString());
                } catch (err) {
                    leaderboardList.innerHTML = '<div class="friends-empty friends-empty--inline"><p>' + escapeHtml(I18N.lbFailed.replace('{error}', err.message)) + '</p></div>';
                } finally {
                    leaderboardPeriodButtons.forEach(btn => { btn.disabled = false; });
                }
            }

            leaderboardPeriodButtons.forEach(btn => {
                btn.addEventListener('click', () => loadLeaderboard(btn.dataset.period));
            });

            container.addEventListener('click', async e => {
                const card = findCard(e.target);
                if (!card) return;
                const uid   = parseInt(card.dataset.userId, 10);
                const reqId = parseInt(card.dataset.requestId, 10);

                try {
                    if (e.target.closest('.js-accept'))         { await postAction('accept', { request_id: reqId }); card.remove(); }
                    else if (e.target.closest('.js-reject'))    { await postAction('reject', { request_id: reqId }); card.remove(); }
                    else if (e.target.closest('.js-cancel'))    { await postAction('cancel', { request_id: reqId }); card.remove(); }
                    else if (e.target.closest('.js-unfriend')) {
                        if (!(await showConfirm({ message: 'Remove this friend?', danger: true }))) return;
                        await postAction('unfriend', { target_id: uid });
                        card.remove();
                    }
                    else if (e.target.closest('.js-send')) {
                        const btn = e.target.closest('.js-send');
                        btn.disabled = true;
                        btn.innerHTML = '<i class="fas fa-clock"></i> Requested';
                        await postAction('send', { target_id: uid });
                        btn.outerHTML = '<span class="friend-card__hint">' + escapeHtml(I18N.pending) + '</span>';
                    }
                    else if (e.target.closest('.js-accept-by-user')) {
                        // From search tab — we don't have request_id locally; look it up.
                        const pending = await postAction('list_pending_in', {});
                        const match = (pending.pending || []).find(p => parseInt(p.user_id, 10) === uid);
                        if (!match) throw new Error('Request not found anymore.');
                        await postAction('accept', { request_id: match.request_id });
                        const btn = e.target.closest('.js-accept-by-user');
                        btn.outerHTML = '<span class="friend-card__hint friend-card__hint--ok"><i class="fas fa-check"></i> ' + escapeHtml(I18N.friends) + '</span>';
                    }
                } catch (err) {
                    flashError(err.message);
                }
            });

            // -------- Search (debounced) --------
            const searchInput = document.getElementById('friendsSearchInput');
            const resultsGrid = document.getElementById('searchResultsGrid');
            let searchTimer = null;

            async function runSearch(q) {
                if (!q || q.trim().length < 2) {
                    resultsGrid.innerHTML = '<div class="friends-empty friends-empty--inline"><p>' + escapeHtml(I18N.startTyping) + '</p></div>';
                    return;
                }
                resultsGrid.innerHTML = '<div class="friends-empty friends-empty--inline"><p>' + escapeHtml(I18N.searching) + '</p></div>';
                try {
                    const data = await postAction('search', { q: q.trim() });
                    if (!data.results || data.results.length === 0) {
                        resultsGrid.innerHTML = '<div class="friends-empty friends-empty--inline"><p>No users matched that name.</p></div>';
                        return;
                    }
                    resultsGrid.innerHTML = data.results.map(u => renderSearchCard(u)).join('');
                } catch (err) {
                    resultsGrid.innerHTML = '<div class="friends-empty friends-empty--inline"><p>Search failed: ' + err.message + '</p></div>';
                }
            }

            function renderSearchCard(u) {
                const name = String(u.user_name || '').replace(/[<>&]/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[c]));
                const level = parseInt(u.current_level || 1, 10);
                const streak = parseInt(u.logging_streak || 0, 10);
                const avatar = u.profile_image
                    ? '<img src="<?= BASE_URL ?>' + u.profile_image + '" alt="">'
                    : '<i class="fas fa-user"></i>';
                let cta;
                switch (u.relationship) {
                    case 'friends':
                        cta = '<span class="friend-card__hint friend-card__hint--ok"><i class="fas fa-check"></i> Friends</span>';
                        break;
                    case 'pending_out':
                        cta = '<span class="friend-card__hint">Pending</span>';
                        break;
                    case 'pending_in':
                        cta = '<button class="btn-tactile btn-tactile--primary js-accept-by-user">Accept</button>';
                        break;
                    default:
                        cta = '<button class="btn-tactile btn-tactile--primary js-send"><i class="fas fa-user-plus"></i> Add Friend</button>';
                }
                return `
                    <article class="friend-card" data-user-id="${u.user_id}" data-request-id="0">
                        <div class="friend-card__avatar">${avatar}<span class="friend-card__level">Lv ${level}</span></div>
                        <div class="friend-card__body">
                            <h3 class="friend-card__name">${name}</h3>
                            <div class="friend-card__stats">
                                ${streak > 0 ? '<span class="friend-card__stat"><i class="fas fa-fire"></i> ' + streak + 'd</span>' : ''}
                            </div>
                        </div>
                        <div class="friend-card__actions">${cta}</div>
                    </article>`;
            }

            if (searchInput) {
                searchInput.addEventListener('input', () => {
                    clearTimeout(searchTimer);
                    const q = searchInput.value;
                    searchTimer = setTimeout(() => runSearch(q), 250);
                });
            }

            // ===================== LIVE POLLING =====================
            // RMIT shared hosting can't do WebSocket/SSE, so we short-poll the
            // friends + pending snapshot and re-render only when membership changes.
            const friendsPanel = container.querySelector('[data-panel="friends"]');
            const pendingPanel = container.querySelector('[data-panel="pending"]');

            function renderFriendCard(u, context) {
                const name = escapeHtml(u.user_name);
                const level = toNumber(u.current_level) || 1;
                const streak = toNumber(u.logging_streak);
                const weekly = (u.weekly_xp !== undefined && u.weekly_xp !== null) ? toNumber(u.weekly_xp) : null;
                const uid = toNumber(u.user_id);
                const reqId = toNumber(u.request_id);
                const avatar = u.profile_image
                    ? '<img src="<?= BASE_URL ?>' + escapeHtml(u.profile_image) + '" alt="">'
                    : '<i class="fas fa-user"></i>';
                let actions = '';
                if (context === 'friend') {
                    actions = '<button class="btn-tactile btn-tactile--ghost js-unfriend" data-csrf="' + CSRF + '"><i class="fas fa-user-minus"></i> Remove</button>';
                } else if (context === 'pending_in') {
                    actions = '<button class="btn-tactile btn-tactile--primary js-accept" data-csrf="' + CSRF + '"><i class="fas fa-check"></i> Accept</button>'
                            + '<button class="btn-tactile btn-tactile--ghost js-reject" data-csrf="' + CSRF + '">Decline</button>';
                } else if (context === 'pending_out') {
                    actions = '<span class="friend-card__hint">Waiting…</span>'
                            + '<button class="btn-tactile btn-tactile--ghost js-cancel" data-csrf="' + CSRF + '">Cancel</button>';
                }
                const stats =
                    (streak > 0 ? '<span class="friend-card__stat"><i class="fas fa-fire"></i> ' + streak + 'd</span>' : '') +
                    (weekly !== null ? '<span class="friend-card__stat"><i class="fas fa-bolt"></i> ' + formatNumber(weekly) + ' XP / 7d</span>' : '');
                return `
                    <article class="friend-card" data-user-id="${uid}" data-request-id="${reqId}">
                        <div class="friend-card__avatar">${avatar}<span class="friend-card__level">Lv ${level}</span></div>
                        <div class="friend-card__body">
                            <h3 class="friend-card__name">${name}</h3>
                            <div class="friend-card__stats">${stats}</div>
                        </div>
                        <div class="friend-card__actions">${actions}</div>
                    </article>`;
            }

            function renderFriendsPanel(friends) {
                if (!friends || friends.length === 0) {
                    return `
                        <div class="friends-empty">
                            <div class="friends-empty__icon"><i class="fas fa-users-slash"></i></div>
                            <h3>No friends yet</h3>
                            <p>Head to <button class="link-button" data-jump-tab="find">Find People</button> and search by username.</p>
                        </div>`;
                }
                return '<div class="friends-grid" id="friendsGrid">' + friends.map(f => renderFriendCard(f, 'friend')).join('') + '</div>';
            }

            function renderPendingPanel(pendingIn, pendingOut) {
                const inHtml = (pendingIn && pendingIn.length)
                    ? '<div class="friends-grid" id="pendingInGrid">' + pendingIn.map(p => renderFriendCard(p, 'pending_in')).join('') + '</div>'
                    : '<div class="friends-empty friends-empty--inline"><p>No incoming requests.</p></div>';
                const outHtml = (pendingOut && pendingOut.length)
                    ? '<div class="friends-grid" id="pendingOutGrid">' + pendingOut.map(p => renderFriendCard(p, 'pending_out')).join('') + '</div>'
                    : '<div class="friends-empty friends-empty--inline"><p>No outgoing requests.</p></div>';
                return '<div class="friends-subhead">Requests for you</div>' + inHtml
                     + '<div class="friends-subhead friends-subhead--spaced">Requests you sent</div>' + outHtml;
            }

            function updateCounts(friends, pendingIn, pendingOut) {
                const fc = friends.length;
                const pc = pendingIn.length + pendingOut.length;

                const metrics = container.querySelectorAll('.friends-metric strong');
                if (metrics[0]) metrics[0].textContent = fc;
                if (metrics[1]) metrics[1].textContent = pc;

                const friendsTabBadge = container.querySelector('.friends-tab[data-tab="friends"] .friends-tab__badge');
                if (friendsTabBadge) friendsTabBadge.textContent = fc;

                const pendingTab = container.querySelector('.friends-tab[data-tab="pending"]');
                let pendingBadge = pendingTab ? pendingTab.querySelector('.friends-tab__badge') : null;
                if (pc > 0) {
                    if (!pendingBadge && pendingTab) {
                        pendingBadge = document.createElement('span');
                        pendingBadge.className = 'friends-tab__badge friends-tab__badge--alert';
                        pendingTab.appendChild(pendingBadge);
                    }
                    if (pendingBadge) pendingBadge.textContent = pc;
                } else if (pendingBadge) {
                    pendingBadge.remove();
                }

                // Sidebar badge mirrors INCOMING pending only (matches server logic).
                const sidebarLink = document.querySelector('.sidebar a[href*="dashboard-friends.php"]');
                if (sidebarLink) {
                    let sb = sidebarLink.querySelector('.nav-link__badge');
                    if (pendingIn.length > 0) {
                        if (!sb) {
                            sb = document.createElement('span');
                            sb.className = 'nav-link__badge';
                            sidebarLink.appendChild(sb);
                        }
                        sb.textContent = pendingIn.length;
                    } else if (sb) {
                        sb.remove();
                    }
                }
            }

            // Signature = sorted id sets only → re-render on add/remove, not on
            // pure XP reordering (keeps the UI calm).
            function stateSig(state) {
                const sortNums = arr => arr.map(x => toNumber(x)).sort((a, b) => a - b);
                return JSON.stringify({
                    f: sortNums((state.friends || []).map(x => x.user_id)),
                    pi: sortNums((state.pending_in || []).map(x => x.request_id)),
                    po: sortNums((state.pending_out || []).map(x => x.request_id))
                });
            }

            // Seed from the server-rendered state so the first poll doesn't re-render needlessly.
            let lastSig = stateSig({
                friends: <?= json_encode(array_map(fn($f) => ['user_id' => (int) $f['user_id']], $friends)) ?>,
                pending_in: <?= json_encode(array_map(fn($p) => ['request_id' => (int) $p['request_id']], $pendingIn)) ?>,
                pending_out: <?= json_encode(array_map(fn($p) => ['request_id' => (int) $p['request_id']], $pendingOut)) ?>
            });

            function applyState(state) {
                const friends = state.friends || [];
                const pendingIn = state.pending_in || [];
                const pendingOut = state.pending_out || [];
                updateCounts(friends, pendingIn, pendingOut);
                if (friendsPanel) friendsPanel.innerHTML = renderFriendsPanel(friends);
                if (pendingPanel) pendingPanel.innerHTML = renderPendingPanel(pendingIn, pendingOut);
            }

            let polling = false;
            async function pollState() {
                if (polling || document.visibilityState !== 'visible') return;
                polling = true;
                try {
                    const data = await postAction('poll', {});
                    const state = {
                        friends: data.friends || [],
                        pending_in: data.pending_in || [],
                        pending_out: data.pending_out || []
                    };
                    const s = stateSig(state);
                    if (s !== lastSig) {
                        lastSig = s;
                        applyState(state);
                    }
                } catch (e) {
                    /* transient network error — stay quiet, try again next tick */
                } finally {
                    polling = false;
                }
            }

            setInterval(pollState, 12000);
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') pollState();
            });
        })();
    </script>
</body>
</html>
