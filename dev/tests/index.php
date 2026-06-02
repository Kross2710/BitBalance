<?php
/**
 * Web UI Dashboard for BitBalance Test Runner.
 * Interactive, gamified, Duolingo-inspired 3D tactile interface.
 */

if (isset($_GET['action']) && $_GET['action'] === 'run') {
    header('Content-Type: application/json');
    try {
        require_once __DIR__ . '/bootstrap.php';
        require_once __DIR__ . '/framework/TestRunner.php';
        
        $targetSuite = !empty($_GET['suite']) ? $_GET['suite'] : null;
        $runner = new TestRunner();
        $results = $runner->run($targetSuite);
        echo json_encode($results);
    } catch (Throwable $e) {
        echo json_encode([
            'error' => true,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BitBalance — Test Laboratory</title>
    <link rel="stylesheet" href="../../css/tokens.css">
    <link rel="stylesheet" href="../../css/base.css">
    <style>
        :root {
            --lab-gradient: linear-gradient(135deg, #1CB0F6, #0079b8);
            --lab-gradient-soft: rgba(28, 176, 246, 0.1);
        }

        body {
            background-color: var(--color-bg);
            color: var(--color-text);
            font-family: var(--font-family-sans);
            padding: 40px 20px;
            margin: 0;
            min-height: 100vh;
            transition: background-color var(--transition-base), color var(--transition-base);
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        /* --- Header --- */
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px dashed var(--color-border);
        }

        .header-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header-title h1 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .lab-badge {
            background: var(--lab-gradient);
            color: white;
            padding: 4px 12px;
            border-radius: var(--radius-pill);
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 2px 4px rgba(28, 176, 246, 0.3);
        }

        .theme-toggle {
            background: var(--color-surface);
            border: 2px solid var(--color-border);
            border-radius: var(--radius-md);
            padding: 8px 16px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 0 var(--color-border-subtle);
            transition: all 0.1s ease;
        }

        .theme-toggle:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 0 var(--color-border-subtle);
        }

        .theme-toggle:active {
            transform: translateY(4px);
            box-shadow: 0 0 0 transparent;
        }

        /* --- Hero Stats Card --- */
        .stats-card {
            background-color: var(--color-surface);
            border: 2px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: 32px;
            margin-bottom: 30px;
            box-shadow: 0 8px 0 var(--color-border-subtle), var(--shadow-sm);
            transition: all var(--transition-base);
            position: relative;
            overflow: hidden;
        }

        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 8px;
            height: 100%;
            background: var(--lab-gradient);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 24px;
        }

        .stat-box {
            text-align: center;
            padding: 16px;
            border-radius: var(--radius-md);
            background: var(--color-surface-alt);
            border: 2px solid var(--color-border);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 4px;
            font-feature-settings: "tnum";
        }

        .stat-label {
            font-size: 0.85rem;
            color: var(--color-text-secondary);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* --- Progress Bar --- */
        .progress-container {
            margin-top: 24px;
        }

        .progress-bar-outer {
            height: 20px;
            background-color: var(--color-surface-alt);
            border: 2px solid var(--color-border);
            border-radius: var(--radius-pill);
            overflow: hidden;
            position: relative;
        }

        .progress-bar-inner {
            height: 100%;
            width: 0%;
            background: var(--gradient-success);
            border-radius: var(--radius-pill);
            transition: width 0.4s cubic-bezier(0.1, 0.8, 0.3, 1);
        }

        /* --- Controls --- */
        .controls {
            display: flex;
            gap: 16px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .btn {
            font-family: inherit;
            font-weight: 800;
            font-size: 1rem;
            padding: 14px 28px;
            border-radius: var(--radius-md);
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.1s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-primary {
            background-color: var(--color-primary);
            color: #ffffff;
            box-shadow: 0 4px 0 var(--color-primary-hover);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 0 var(--color-primary-hover);
        }

        .btn-primary:active {
            transform: translateY(4px);
            box-shadow: 0 0 0 transparent;
        }

        .btn-primary:disabled {
            background-color: var(--color-text-muted);
            box-shadow: 0 4px 0 var(--color-border);
            cursor: not-allowed;
            transform: none !important;
        }

        .search-box {
            flex-grow: 1;
            display: flex;
            position: relative;
        }

        .search-input {
            width: 100%;
            border: 2px solid var(--color-border);
            border-radius: var(--radius-md);
            background-color: var(--color-surface);
            color: var(--color-text);
            padding: 12px 16px;
            font-weight: 600;
            font-size: 1rem;
            transition: all var(--transition-base);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--color-secondary);
            box-shadow: 0 0 0 4px rgba(28, 176, 246, 0.15);
        }

        /* --- Suites Grid --- */
        .suites-grid {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .suite-card {
            background-color: var(--color-surface);
            border: 2px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: 24px;
            box-shadow: 0 6px 0 var(--color-border-subtle), var(--shadow-sm);
            transition: all var(--transition-base);
        }

        .suite-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 0 var(--color-border-subtle), var(--shadow-md);
        }

        .suite-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            cursor: pointer;
        }

        .suite-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .suite-title h3 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 800;
        }

        .suite-meta {
            font-size: 0.85rem;
            color: var(--color-text-secondary);
            font-weight: 700;
        }

        .status-pill {
            padding: 4px 12px;
            border-radius: var(--radius-pill);
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .status-pill.passed {
            background-color: var(--color-success-bg);
            color: var(--color-success);
            border: 1px solid var(--color-success-border);
        }

        .status-pill.failed {
            background-color: var(--color-danger-bg);
            color: var(--color-danger);
            border: 1px solid var(--color-danger-border);
        }

        .status-pill.pending {
            background-color: var(--color-surface-alt);
            color: var(--color-text-secondary);
            border: 1px solid var(--color-border);
        }

        /* --- Cases List --- */
        .cases-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            border-top: 2px dashed var(--color-border);
            padding-top: 16px;
        }

        .case-row {
            display: flex;
            flex-direction: column;
            background: var(--color-surface-alt);
            border: 2px solid var(--color-border);
            border-radius: var(--radius-md);
            padding: 12px 16px;
            transition: all var(--transition-fast);
        }

        .case-row-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .case-info {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
        }

        .case-status-icon {
            font-size: 1.1rem;
        }

        .case-status-icon.passed { color: var(--color-success); }
        .case-status-icon.failed { color: var(--color-danger); }

        .case-duration {
            font-size: 0.8rem;
            color: var(--color-text-secondary);
            font-weight: 600;
        }

        /* --- Error details --- */
        .case-error {
            margin-top: 12px;
            background: var(--color-surface);
            border: 2px solid var(--color-danger-border);
            border-radius: var(--radius-sm);
            padding: 14px;
            font-family: monospace;
            font-size: 0.85rem;
            overflow-x: auto;
            color: var(--color-danger);
        }

        .error-message {
            font-weight: 700;
            margin-bottom: 8px;
            white-space: pre-wrap;
        }

        .error-file {
            font-size: 0.8rem;
            color: var(--color-text-secondary);
            margin-bottom: 12px;
            border-bottom: 1px solid var(--color-border);
            padding-bottom: 8px;
        }

        .diff-block {
            display: grid;
            grid-template-columns: minmax(80px, auto) 1fr;
            gap: 8px 16px;
            background: var(--color-surface-alt);
            padding: 10px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--color-border);
        }

        .diff-label {
            font-weight: 700;
            color: var(--color-text-secondary);
        }

        .diff-value {
            white-space: pre-wrap;
            word-break: break-all;
        }

        /* --- Empty State --- */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--color-surface);
            border: 2px dashed var(--color-border);
            border-radius: var(--radius-lg);
            font-weight: 700;
            color: var(--color-text-secondary);
        }

        .empty-state h3 {
            margin: 0 0 10px 0;
            font-size: 1.4rem;
            color: var(--color-text);
        }

        /* --- Animations --- */
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .running-pulse {
            animation: pulse 1.5s infinite ease-in-out;
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Header -->
    <header>
        <div class="header-title">
            <h1>BitBalance Test Laboratory</h1>
            <span class="lab-badge">Unit & Integration</span>
        </div>
        <button class="theme-toggle" id="theme-toggle">
            <span id="theme-icon">🌙</span> <span id="theme-text">Dark Mode</span>
        </button>
    </header>

    <!-- Stats Hero -->
    <div class="stats-card">
        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-value" id="stat-total">0</div>
                <div class="stat-label">Total Tests</div>
            </div>
            <div class="stat-box" style="border-color: var(--color-success-border);">
                <div class="stat-value" id="stat-passed" style="color: var(--color-success);">0</div>
                <div class="stat-label">Passed</div>
            </div>
            <div class="stat-box" style="border-color: var(--color-danger-border);">
                <div class="stat-value" id="stat-failed" style="color: var(--color-danger);">0</div>
                <div class="stat-label">Failed</div>
            </div>
            <div class="stat-box">
                <div class="stat-value" id="stat-duration">0.00ms</div>
                <div class="stat-label">Duration</div>
            </div>
        </div>

        <div class="progress-container">
            <div class="progress-bar-outer">
                <div class="progress-bar-inner" id="progress-bar"></div>
            </div>
        </div>
    </div>

    <!-- Controls -->
    <div class="controls">
        <button class="btn btn-primary" id="run-btn">
            <span>🚀</span> Run All Tests
        </button>
        <a class="btn" href="i18n.php" style="text-decoration: none; background-color: var(--color-surface); color: var(--color-text); border: 2px solid var(--color-border); box-shadow: 0 4px 0 var(--color-border);">
            <span>🌐</span> i18n Parity
        </a>
        <div class="search-box">
            <input type="text" class="search-input" id="search-input" placeholder="Search test suites (e.g. UsernameTest)...">
        </div>
    </div>

    <!-- Suites -->
    <div class="suites-grid" id="suites-container">
        <!-- Will be dynamically populated -->
        <div class="empty-state">
            <h3>Ready to Validate</h3>
            <p>Click "Run All Tests" above to execute all BitBalance test suites inside safe database transactions.</p>
        </div>
    </div>
</div>

<script>
    // Theme Management
    const themeToggleBtn = document.getElementById('theme-toggle');
    const themeIcon = document.getElementById('theme-icon');
    const themeText = document.getElementById('theme-text');

    function toggleTheme() {
        const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
        const newTheme = currentTheme === 'light' ? 'dark' : 'light';
        
        document.documentElement.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        updateThemeUI(newTheme);
    }

    function updateThemeUI(theme) {
        if (theme === 'dark') {
            themeIcon.textContent = '☀️';
            themeText.textContent = 'Light Mode';
        } else {
            themeIcon.textContent = '🌙';
            themeText.textContent = 'Dark Mode';
        }
    }

    // Set initial theme
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
    updateThemeUI(savedTheme);

    themeToggleBtn.addEventListener('click', toggleTheme);

    // Test Runner Logic
    const runBtn = document.getElementById('run-btn');
    const searchInput = document.getElementById('search-input');
    const suitesContainer = document.getElementById('suites-container');
    const progressBar = document.getElementById('progress-bar');
    
    const statTotal = document.getElementById('stat-total');
    const statPassed = document.getElementById('stat-passed');
    const statFailed = document.getElementById('stat-failed');
    const statDuration = document.getElementById('stat-duration');

    let allSuitesData = null;

    async function runTests() {
        runBtn.disabled = true;
        runBtn.innerHTML = '<span>⚡</span> Running...';
        runBtn.classList.add('running-pulse');
        
        // Dynamic loading state
        suitesContainer.innerHTML = `
            <div class="empty-state">
                <h3 class="running-pulse">Running Test Laboratory</h3>
                <p>Initializing mock test users and executing transactional verification checks...</p>
            </div>
        `;
        progressBar.style.width = '20%';
        progressBar.style.background = 'var(--lab-gradient)';

        try {
            const response = await fetch('index.php?action=run');
            const data = await response.json();
            
            if (data.error) {
                alert('Testing error: ' + data.message);
                resetRunBtn();
                return;
            }

            allSuitesData = data.suites;
            progressBar.style.width = '100%';
            
            if (data.stats.failed > 0) {
                progressBar.style.background = 'var(--gradient-streak)';
            } else {
                progressBar.style.background = 'var(--gradient-success)';
            }

            // Update stats
            statTotal.textContent = data.stats.total;
            statPassed.textContent = data.stats.passed;
            statFailed.textContent = data.stats.failed;
            statDuration.textContent = data.stats.duration + 'ms';

            renderSuites(data.suites);

        } catch (error) {
            console.error('Failed fetching tests', error);
            suitesContainer.innerHTML = `
                <div class="empty-state" style="border-color: var(--color-danger-border);">
                    <h3 style="color: var(--color-danger);">Execution Error</h3>
                    <p>Failed to connect to the test laboratory endpoint. Ensure MySQL is running on loopback 127.0.0.1.</p>
                </div>
            `;
            progressBar.style.width = '0%';
        } finally {
            resetRunBtn();
        }
    }

    function resetRunBtn() {
        runBtn.disabled = false;
        runBtn.innerHTML = '<span>🚀</span> Run All Tests';
        runBtn.classList.remove('running-pulse');
    }

    function renderSuites(suites, filter = '') {
        const query = filter.toLowerCase().trim();
        suitesContainer.innerHTML = '';
        
        let renderedCount = 0;

        for (const [suiteName, suite] of Object.entries(suites)) {
            if (query && !suiteName.toLowerCase().includes(query)) {
                continue;
            }
            
            renderedCount++;

            const statusClass = suite.failed > 0 ? 'failed' : 'passed';
            const statusText = suite.failed > 0 ? `${suite.failed} Failed` : 'Passed';

            const suiteCard = document.createElement('div');
            suiteCard.className = 'suite-card';
            
            let casesHtml = '';
            suite.cases.forEach(c => {
                const cStatus = c.status;
                const cIcon = cStatus === 'passed' ? '✔' : '✘';
                
                let errorHtml = '';
                if (c.error) {
                    let diffHtml = '';
                    if (c.error.expected !== null || c.error.actual !== null) {
                        diffHtml = `
                            <div class="diff-block">
                                <div class="diff-label">Expected:</div>
                                <div class="diff-value">${escapeHtml(JSON.stringify(c.error.expected))}</div>
                                <div class="diff-label">Actual:</div>
                                <div class="diff-value" style="color: var(--color-danger);">${escapeHtml(JSON.stringify(c.error.actual))}</div>
                            </div>
                        `;
                    }
                    
                    errorHtml = `
                        <div class="case-error">
                            <div class="error-message">${escapeHtml(c.error.message)}</div>
                            <div class="error-file">In ${escapeHtml(c.error.file)} on line ${c.error.line}</div>
                            ${diffHtml}
                        </div>
                    `;
                }

                casesHtml += `
                    <div class="case-row">
                        <div class="case-row-header">
                            <div class="case-info">
                                <span class="case-status-icon ${cStatus}">${cIcon}</span>
                                <span>${c.method}</span>
                            </div>
                            <span class="case-duration">${c.duration}ms</span>
                        </div>
                        ${errorHtml}
                    </div>
                `;
            });

            suiteCard.innerHTML = `
                <div class="suite-header" onclick="toggleSuiteBody(this)">
                    <div class="suite-title">
                        <h3>${suiteName}</h3>
                        <span class="status-pill ${statusClass}">${statusText}</span>
                    </div>
                    <div class="suite-meta">
                        <span>${suite.total} test cases</span> • 
                        <span>${suite.duration}ms</span>
                    </div>
                </div>
                <div class="cases-list" style="display: block;">
                    ${casesHtml}
                </div>
            `;
            
            suitesContainer.appendChild(suiteCard);
        }

        if (renderedCount === 0) {
            suitesContainer.innerHTML = `
                <div class="empty-state">
                    <h3>No Suites Found</h3>
                    <p>No test suites matched your search criteria "${filter}".</p>
                </div>
            `;
        }
    }

    function toggleSuiteBody(header) {
        const body = header.nextElementSibling;
        if (body.style.display === 'none') {
            body.style.display = 'flex';
        } else {
            body.style.display = 'none';
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Event listeners
    runBtn.addEventListener('click', runTests);
    
    searchInput.addEventListener('input', (e) => {
        if (allSuitesData) {
            renderSuites(allSuitesData, e.target.value);
        }
    });
</script>
</body>
</html>
