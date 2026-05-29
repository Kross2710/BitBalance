<div class="right-sidebar">
    <div class="sidebar-section user-welcome">
        <div class="date-badge">
            <i class="far fa-calendar-alt"></i>
            <?php
            // Emit server's current moment as ISO with VN offset; client converts
            // to visitor's local date so "today" reflects the user's calendar day.
            $nowIso = (new DateTime('now', new DateTimeZone('Asia/Ho_Chi_Minh')))->format('c');
            ?>
            <span data-iso="<?= $nowIso ?>" data-tz-format="date-long"><?= date('j F, Y') ?></span>
        </div>
        <h2>Hello, <br><span class="user-name"><?php echo htmlspecialchars($displayUser); ?></span></h2>
    </div>

    <hr class="divider">

    <div class="sidebar-section user-metrics">
        <div class="section-title">
            <i class="fas fa-child"></i> Body Metrics
        </div>
        
        <?php if (empty($userAge) || empty($userWeight) || empty($userHeight)): ?>
            <div class="empty-metrics">
                <p>Missing info.</p>
                <a href="profile.php" class="btn-text">Update Profile</a>
            </div>
        <?php else: ?>
            <div class="metrics-grid">
                <div class="metric-box">
                    <div class="metric-icon age-icon"><i class="fas fa-birthday-cake"></i></div>
                    <span class="metric-val"><?php echo htmlspecialchars((int)$userAge); ?></span>
                    <span class="metric-label">Age</span>
                </div>
                <div class="metric-box">
                    <div class="metric-icon weight-icon"><i class="fas fa-weight"></i></div>
                    <span class="metric-val"><?php echo htmlspecialchars((int)$userWeight); ?></span>
                    <span class="metric-label">kg</span>
                </div>
                <div class="metric-box">
                    <div class="metric-icon height-icon"><i class="fas fa-ruler-vertical"></i></div>
                    <span class="metric-val"><?php echo htmlspecialchars((int)$userHeight); ?></span>
                    <span class="metric-label">cm</span>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <hr class="divider">

    <div class="sidebar-section goal-summary">
        <div class="section-title">
            <i class="fas fa-bullseye"></i> Daily Target
        </div>
        
        <div class="goal-card-mini">
            <?php if (!empty($userGoal)): ?>
                <?php $remaining = max(0, $userGoal - $totalCalories); ?>
                
                <div class="goal-row">
                    <span class="goal-label">Target:</span>
                    <span class="goal-val"><?= htmlspecialchars($userGoal) ?></span>
                </div>
                
                <div class="goal-row remaining-row">
                    <span class="goal-label">Remaining:</span>
                    <span class="goal-val <?= $remaining <= 0 ? 'text-success' : 'text-danger' ?>">
                        <?= htmlspecialchars($remaining) ?>
                    </span>
                </div>
            <?php else: ?>
                <p class="no-goal-text">No goal set yet.</p>
                <a href="set-goal.php" class="btn-small">Set Goal</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (($activePage ?? '') === 'overview'): ?>
        <hr class="divider">

        <div class="sidebar-section leaderboard-mini">
            <div class="section-title">
                <i class="fas fa-trophy"></i> Weekly Top 5
            </div>

            <?php if (!empty($isLoggedIn) && !empty($leaderboardWidgetRows)): ?>
                <div class="leaderboard-mini-list">
                    <?php foreach ($leaderboardWidgetRows as $row): ?>
                        <?php
                        $rank = (int) ($row['rank'] ?? 0);
                        $rankClass = $rank === 1 ? ' leaderboard-mini-rank--gold' : ($rank === 2 ? ' leaderboard-mini-rank--silver' : ($rank === 3 ? ' leaderboard-mini-rank--bronze' : ''));
                        $isYou = !empty($row['is_current_user']);
                        $avatar = $row['profile_image'] ?? '';
                        ?>
                        <a
                            class="leaderboard-mini-row<?= $isYou ? ' leaderboard-mini-row--you' : '' ?>"
                            href="<?= BASE_URL ?>dashboard/dashboard-friends.php?tab=leaderboard"
                        >
                            <span class="leaderboard-mini-rank<?= $rankClass ?>">
                                <?php if ($rank <= 3): ?>
                                    <i class="fas <?= $rank === 1 ? 'fa-trophy' : 'fa-medal' ?>"></i>
                                <?php else: ?>
                                    <?= $rank ?>
                                <?php endif; ?>
                            </span>
                            <span class="leaderboard-mini-avatar">
                                <?php if ($avatar): ?>
                                    <img src="<?= BASE_URL . htmlspecialchars($avatar, ENT_QUOTES) ?>" alt="">
                                <?php else: ?>
                                    <i class="fas fa-user"></i>
                                <?php endif; ?>
                            </span>
                            <span class="leaderboard-mini-main">
                                <span class="leaderboard-mini-name">
                                    <span class="leaderboard-mini-user"><?= htmlspecialchars($row['user_name'] ?? '', ENT_QUOTES) ?></span>
                                    <?php if ($isYou): ?><span class="leaderboard-mini-you">You</span><?php endif; ?>
                                </span>
                                <span class="leaderboard-mini-score"><?= number_format((int) ($row['score_xp'] ?? 0)) ?> XP</span>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php elseif (!empty($isLoggedIn)): ?>
                <div class="leaderboard-mini-empty">
                    <p>No leaderboard data yet.</p>
                    <a href="<?= BASE_URL ?>dashboard/dashboard-friends.php?tab=friends" class="btn-small">Open Friends</a>
                </div>
            <?php else: ?>
                <div class="leaderboard-mini-empty">
                    <p>Sign in to compete with friends.</p>
                    <a href="<?= BASE_URL ?>login.php" class="btn-small">Sign in</a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
