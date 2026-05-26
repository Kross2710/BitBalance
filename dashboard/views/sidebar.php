<div class="sidebar">
    <a href="dashboard.php" class="nav-link <?php echo ($activePage == 'overview') ? 'active' : ''; ?>">
        <i class="fas fa-th-large"></i> Overview
    </a>
    
    <a href="dashboard-intake.php" class="nav-link <?php echo ($activePage == 'intake') ? 'active' : ''; ?>" data-short="Intake">
        <i class="fas fa-utensils"></i> Food Intake
    </a>

    <a href="dashboard-history.php" class="nav-link <?php echo ($activePage == 'history') ? 'active' : ''; ?>">
        <i class="fas fa-history"></i> History
    </a>

    <a href="dashboard-calculator.php" class="nav-link <?php echo ($activePage == 'calculator') ? 'active' : ''; ?>" data-short="Calc">
        <i class="fas fa-calculator"></i> Calculator
    </a>

    <a href="dashboard-wiki.php" class="nav-link <?php echo ($activePage == 'wiki') ? 'active' : ''; ?>">
        <i class="fas fa-book-medical"></i> Wiki
    </a>
</div>

