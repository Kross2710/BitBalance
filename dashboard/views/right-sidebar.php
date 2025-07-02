<div class="right-sidebar">
    <div class="info user-info">
        <h2>Welcome, <span style="color: #4a7ee3;"><?php echo htmlspecialchars($user['user_name']); ?></span></h2>
        <p>Today's Date: <br> <?php echo date('j F, Y'); ?></p>
    </div>
    <div class="info user-physical-info">
        <h3>Your Body Metrics</h3>
        <?php if (empty($userAge) || empty($userWeight) || empty($userHeight)): ?>
            <p style="color: #d32f2f;">Please complete your info in your profile settings.</p>
        <?php else: ?>
            <p>Age: <?php echo htmlspecialchars((int)$userAge); ?></p>
            <p>Weight: <?php echo htmlspecialchars((int)$userWeight); ?> kg</p>
            <p>Height: <?php echo htmlspecialchars((int)$userHeight); ?> cm</p>
        <?php endif; ?>
    </div>
    <div class="info user-goal">
        <h3>Your Daily Goal</h3>
        <p>
            <?php if (!empty($userGoal)): ?>
                <?php
                $remaining = max(0, $userGoal - $totalCalories);
                ?>
                <?= htmlspecialchars($userGoal) ?> calories <br>
                <span style="color: <?= $remaining <= 0 ? '#388e3c' : '#d32f2f' ?>;">
                    (<?= htmlspecialchars($remaining) ?> left to go)
                </span>
            <?php else: ?>
                <span style="color: #d32f2f;">No goal set. <a href="set-goal.php">Set your goal</a></span>
            <?php endif; ?>
        </p>
    </div>
    <style>
        .right-sidebar {
            margin-top: 20px;
            right: 0;
            width: 200px;
            padding: 20px;
            position: fixed;
            border-top-left-radius: 10px;
            border-bottom-left-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .right-sidebar .info {
            margin-bottom: 20px;
            padding: 5px;
            background-color: white;
            margin-bottom: 10px;
        }

        .user-goal p a {
            color: #4a7ee3;
            text-decoration: none;
        }

        .user-goal p a:hover {
            text-decoration: underline;
        }

        @media (max-width: 900px) {
            .right-sidebar {
                display: none; 
            }
        }
    </style>
    <style>
         [data-theme="dark"] .right-sidebar {
        background: #2d2d2d !important;
        border: 1px solid #404040 !important;
        color: #ffffff !important;
    }

    [data-theme="dark"] .right-sidebar .info {
        background-color: #2d2d2d !important;
        color: #ffffff !important;
        border: 1px solid #404040 !important;
    }

    [data-theme="dark"] .right-sidebar .info h2,
    [data-theme="dark"] .right-sidebar .info h3,
    [data-theme="dark"] .right-sidebar .info p {
        color: #ffffff !important;
    }

    [data-theme="dark"] .right-sidebar .info h2 span {
        color: #4a7ee3 !important;
    }

    [data-theme="dark"] .right-sidebar .user-goal p a {
        color: #4a7ee3 !important;
    }

    [data-theme="dark"] .right-sidebar .user-goal p a:hover {
        color: #6c9fff !important;
    }
    </style>
</div>