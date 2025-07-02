<div class="sidebar">
    <a href="dashboard.php" class="<?php echo ($activePage == 'overview') ? 'active' : ''; ?>">Overview</a>
    <a href="dashboard-intake.php" class="<?php echo ($activePage == 'intake') ? 'active' : ''; ?>">Food</a>
    <a href="dashboard-history.php" class="<?php echo ($activePage == 'history') ? 'active' : ''; ?>">History</a>
    <a href="dashboard-calculator.php"
        class="<?php echo ($activePage == 'calculator') ? 'active' : ''; ?>">Calculator</a>
</div>
<style>
    .sidebar {
        width: 200px;
        padding: 20px;
        position: fixed;
        border-top-right-radius: 10px;
        border-bottom-right-radius: 10px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);

        margin-top: 20px;
    }

    .sidebar a {
        display: block;
        padding: 8px;
        color: #333;
        text-decoration: none;
        margin-bottom: 10px;
        border-radius: 5px;
    }

    .sidebar a.active {
        background-color: #4a7ee3;
        color: white;
    }

    .sidebar a:hover {
        background-color: #ddd;
        color: black;
        transition: all 0.3s ease;
    }

    @media (max-width: 900px) {
        .sidebar {
            width: 100vw;
            position: static;
            display: flex;
            flex-direction: row;
            justify-content: space-around;
            padding: 8px 0;
            border-radius: 0;
            box-shadow: none;
            border: none;
            top: 0;
            left: 0;
            z-index: 100;
        }

        .sidebar a {
            flex: 1;
            margin: 0;
            font-size: 1.05rem;
            padding: 14px 0;
            text-align: center;
            border-radius: 0;
            border: none;
        }

        .sidebar a.active {
            background: #4a7ee3;
            color: #fff;
        }
    }

    @media (max-width: 480px) {
        .sidebar a {
            font-size: 0.95rem;
            padding: 10px 0;
        }
    }
</style>