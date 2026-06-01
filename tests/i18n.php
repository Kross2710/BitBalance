<?php
/**
 * tests/i18n.php
 *
 * Read-only i18n parity viewer — a side-by-side matrix of every translation key
 * across all locales (include/i18n/<code>.php), with the gaps the runtime hides
 * flagged inline: missing keys, strings left identical to English (untranslated),
 * "orphan" keys absent from the fallback locale, and {placeholder} mismatches.
 *
 * This page only READS the locale files (via tests/framework/I18nParity.php) and
 * renders. It never writes — to fix something, edit include/i18n/<code>.php and
 * refresh. The same analyzer backs the CLI guard at tests/suites/I18nParityTest.php.
 */

require_once __DIR__ . '/framework/I18nParity.php';

$report  = (new I18nParity())->analyze();
$codes   = $report['codes'];
$locales = $report['locales'];
$fb      = $report['fallback'];
$summary = $report['summary'];

/** Short kind labels for the legend / row tags. */
$KIND_LABEL = array(
    'missing'      => 'Missing',
    'untranslated' => 'Untranslated',
    'orphan'       => 'Orphan (not in ' . strtoupper($fb) . ')',
    'placeholder'  => 'Placeholder mismatch',
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BitBalance — i18n Parity</title>
    <link rel="stylesheet" href="../css/tokens.css">
    <link rel="stylesheet" href="../css/base.css">
    <style>
        :root {
            --lab-gradient: linear-gradient(135deg, #1CB0F6, #0079b8);
        }
        body {
            background-color: var(--color-bg);
            color: var(--color-text);
            font-family: var(--font-family-sans);
            padding: 32px 20px;
            margin: 0;
            min-height: 100vh;
            transition: background-color var(--transition-base), color var(--transition-base);
        }
        .container { max-width: 1180px; margin: 0 auto; }

        header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 8px; flex-wrap: wrap; gap: 12px;
        }
        .header-title { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
        .header-title h1 { margin: 0; font-size: 1.7rem; }
        .lab-badge {
            background: var(--lab-gradient); color: #fff; font-weight: 800;
            font-size: 0.72rem; text-transform: uppercase; letter-spacing: .04em;
            padding: 5px 12px; border-radius: var(--radius-pill);
        }
        .header-actions { display: flex; gap: 10px; align-items: center; }
        .link-btn, .theme-toggle {
            background: var(--color-surface); color: var(--color-text);
            border: 2px solid var(--color-border); border-radius: var(--radius-pill);
            padding: 8px 14px; font-weight: 700; font-size: 0.85rem; cursor: pointer;
            text-decoration: none; display: inline-flex; align-items: center; gap: 6px;
        }
        .subtitle { color: var(--color-text-secondary); font-weight: 600; margin: 0 0 24px; }

        /* --- Summary cards --- */
        .summary-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 14px; margin-bottom: 24px;
        }
        .sum-card {
            background: var(--color-surface); border: 2px solid var(--color-border);
            border-radius: var(--radius-lg); padding: 16px 18px;
        }
        .sum-value { font-size: 1.7rem; font-weight: 900; line-height: 1; }
        .sum-label { font-size: 0.8rem; color: var(--color-text-secondary); font-weight: 700; margin-top: 6px; }
        .sum-card.good   { border-color: var(--color-success-border); }
        .sum-card.good   .sum-value { color: var(--color-success); }
        .sum-card.warn   { border-color: var(--color-danger-border); }
        .sum-card.warn   .sum-value { color: var(--color-danger); }
        .per-locale { font-size: 0.72rem; color: var(--color-text-secondary); margin-top: 8px; font-weight: 700; }

        /* --- Controls --- */
        .controls { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; margin-bottom: 18px; }
        .chips { display: flex; gap: 8px; flex-wrap: wrap; }
        .chip {
            border: 2px solid var(--color-border); background: var(--color-surface);
            color: var(--color-text); border-radius: var(--radius-pill);
            padding: 7px 14px; font-weight: 800; font-size: 0.8rem; cursor: pointer;
            transition: all var(--transition-fast); white-space: nowrap;
        }
        .chip[aria-pressed="true"] { background: var(--lab-gradient); color: #fff; border-color: transparent; }
        .chip .cnt { opacity: .8; font-weight: 700; }
        .search-input {
            flex: 1; min-width: 220px; padding: 10px 14px;
            border: 2px solid var(--color-border); border-radius: var(--radius-md);
            background: var(--color-surface); color: var(--color-text);
            font-size: 0.9rem; font-family: inherit;
        }

        /* --- Table --- */
        .table-wrap {
            border: 2px solid var(--color-border); border-radius: var(--radius-lg);
            overflow: hidden; background: var(--color-surface);
        }
        table { width: 100%; border-collapse: collapse; font-size: 0.86rem; }
        thead th {
            position: sticky; top: 0; z-index: 2;
            background: var(--color-surface-alt); text-align: left;
            padding: 12px 14px; font-weight: 800; border-bottom: 2px solid var(--color-border);
            color: var(--color-text-secondary); text-transform: uppercase; font-size: 0.72rem; letter-spacing: .03em;
        }
        tbody td { padding: 11px 14px; border-bottom: 1px solid var(--color-border); vertical-align: top; }
        tbody tr:hover { background: var(--color-surface-alt); }
        .key-cell { font-family: var(--font-family-mono, monospace); font-weight: 700; cursor: copy; }
        .key-cell:hover { color: var(--color-primary, #1CB0F6); }
        .key-ns { opacity: .55; }
        .val { white-space: pre-wrap; word-break: break-word; max-width: 360px; }
        .val.fallback-col { color: var(--color-text); }
        .val.other { color: var(--color-text); }

        .tag {
            display: inline-block; font-size: 0.66rem; font-weight: 800; text-transform: uppercase;
            letter-spacing: .03em; padding: 2px 7px; border-radius: var(--radius-pill); margin-left: 6px;
            vertical-align: middle;
        }
        .tag.missing      { background: var(--color-danger-bg);  color: var(--color-danger);  border: 1px solid var(--color-danger-border); }
        .tag.untranslated { background: var(--color-surface-alt); color: var(--color-text-secondary); border: 1px solid var(--color-border); }
        .tag.placeholder  { background: var(--color-danger-bg);  color: var(--color-danger);  border: 1px solid var(--color-danger-border); }
        .tag.orphan       { background: var(--color-danger-bg);  color: var(--color-danger);  border: 1px solid var(--color-danger-border); }

        .missing-cell { color: var(--color-text-secondary); font-style: italic; opacity: .8; }
        tr.has-issue .key-cell { box-shadow: inset 3px 0 0 var(--color-danger); padding-left: 11px; }

        .legend { display: flex; gap: 16px; flex-wrap: wrap; margin: 14px 2px 0; font-size: 0.76rem; color: var(--color-text-secondary); font-weight: 600; }
        .legend span b { color: var(--color-text); }
        .empty-row td { text-align: center; padding: 40px; color: var(--color-text-secondary); font-weight: 700; }
        .copied-toast {
            position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%);
            background: var(--color-text); color: var(--color-bg); padding: 10px 18px;
            border-radius: var(--radius-pill); font-weight: 800; font-size: 0.85rem;
            opacity: 0; pointer-events: none; transition: opacity .2s; z-index: 50;
        }
        .copied-toast.show { opacity: 1; }
    </style>
</head>
<body>
<div class="container">
    <header>
        <div class="header-title">
            <h1>i18n Parity</h1>
            <span class="lab-badge">Translation Matrix</span>
        </div>
        <div class="header-actions">
            <a class="link-btn" href="index.php">🧪 Test Lab</a>
            <button class="theme-toggle" id="theme-toggle"><span id="theme-icon">🌙</span> <span id="theme-text">Dark</span></button>
        </div>
    </header>
    <p class="subtitle">
        Read-only view of <b><?php echo count($codes); ?></b> locales ·
        <b><?php echo $summary['unionKeys']; ?></b> unique keys.
        Fallback locale: <b><?php echo strtoupper($fb); ?></b>.
        Edit <code>include/i18n/&lt;code&gt;.php</code> and refresh to update.
    </p>

    <?php
        $totalMissing = 0; $totalUntranslated = 0;
        foreach ($codes as $c) {
            $totalMissing      += $report['stats'][$c]['missing'];
            $totalUntranslated += $report['stats'][$c]['untranslated'];
        }
        $issueFree = ($totalMissing === 0 && $summary['placeholderMismatches'] === 0 && count($summary['orphanKeys']) === 0);
    ?>
    <div class="summary-grid">
        <div class="sum-card">
            <div class="sum-value"><?php echo $summary['unionKeys']; ?></div>
            <div class="sum-label">Total keys</div>
            <div class="per-locale">
                <?php foreach ($codes as $c) { echo strtoupper($c) . ' ' . $report['stats'][$c]['total'] . '&nbsp;&nbsp;'; } ?>
            </div>
        </div>
        <div class="sum-card <?php echo $totalMissing ? 'warn' : 'good'; ?>">
            <div class="sum-value"><?php echo $totalMissing; ?></div>
            <div class="sum-label">Missing translations</div>
            <div class="per-locale">
                <?php foreach ($codes as $c) { echo strtoupper($c) . ' ' . $report['stats'][$c]['missing'] . '&nbsp;&nbsp;'; } ?>
            </div>
        </div>
        <div class="sum-card <?php echo count($summary['orphanKeys']) ? 'warn' : 'good'; ?>">
            <div class="sum-value"><?php echo count($summary['orphanKeys']); ?></div>
            <div class="sum-label">Orphan keys (not in <?php echo strtoupper($fb); ?>)</div>
        </div>
        <div class="sum-card <?php echo $summary['placeholderMismatches'] ? 'warn' : 'good'; ?>">
            <div class="sum-value"><?php echo $summary['placeholderMismatches']; ?></div>
            <div class="sum-label">Placeholder mismatches</div>
        </div>
        <div class="sum-card">
            <div class="sum-value"><?php echo $totalUntranslated; ?></div>
            <div class="sum-label">Same as <?php echo strtoupper($fb); ?> (review)</div>
            <div class="per-locale">
                <?php foreach ($codes as $c) { if ($c === $fb) continue; echo strtoupper($c) . ' ' . $report['stats'][$c]['untranslated'] . '&nbsp;&nbsp;'; } ?>
            </div>
        </div>
    </div>

    <div class="controls">
        <div class="chips" id="chips">
            <button class="chip" data-filter="all" aria-pressed="true">All <span class="cnt">(<?php echo $summary['unionKeys']; ?>)</span></button>
            <button class="chip" data-filter="issues" aria-pressed="false">⚠ Issues <span class="cnt">(<?php echo $summary['rowsWithIssues']; ?>)</span></button>
            <button class="chip" data-filter="missing" aria-pressed="false">Missing</button>
            <button class="chip" data-filter="untranslated" aria-pressed="false">Untranslated</button>
            <button class="chip" data-filter="orphan" aria-pressed="false">Orphan</button>
            <button class="chip" data-filter="placeholder" aria-pressed="false">Placeholder</button>
        </div>
        <input type="text" class="search-input" id="search" placeholder="Search key or text…">
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width: 26%;">Key</th>
                    <?php foreach ($codes as $c): ?>
                        <th><?php echo strtoupper($c); ?> · <?php echo htmlspecialchars($locales[$c]['native'] ?? $c, ENT_QUOTES, 'UTF-8'); ?><?php echo $c === $fb ? ' (fallback)' : ''; ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody id="rows">
                <?php foreach ($report['rows'] as $key => $row):
                    $kinds = $row['kinds'];
                    $hasIssue = !empty($kinds);
                    // Build a lowercase search haystack: key + all values.
                    $hay = strtolower($key);
                    foreach ($row['cells'] as $cell) { if ($cell['present']) { $hay .= ' ' . strtolower($cell['value']); } }
                    $dataKinds = implode(' ', $kinds);
                    // Pretty key: dim the namespace prefix.
                    $dotPos = strrpos($key, '.');
                    if ($dotPos !== false) {
                        $keyHtml = '<span class="key-ns">' . htmlspecialchars(substr($key, 0, $dotPos + 1), ENT_QUOTES, 'UTF-8') . '</span>'
                                 . htmlspecialchars(substr($key, $dotPos + 1), ENT_QUOTES, 'UTF-8');
                    } else {
                        $keyHtml = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
                    }
                ?>
                <tr class="<?php echo $hasIssue ? 'has-issue' : ''; ?>"
                    data-kinds="<?php echo htmlspecialchars($dataKinds, ENT_QUOTES, 'UTF-8'); ?>"
                    data-hay="<?php echo htmlspecialchars($hay, ENT_QUOTES, 'UTF-8'); ?>">
                    <td class="key-cell" title="Click to copy"><?php echo $keyHtml; ?>
                        <?php if (in_array('orphan', $kinds, true)): ?><span class="tag orphan">orphan</span><?php endif; ?>
                        <?php if (in_array('placeholder', $kinds, true)): ?><span class="tag placeholder">{ } mismatch</span><?php endif; ?>
                    </td>
                    <?php foreach ($codes as $c):
                        $cell = $row['cells'][$c];
                    ?>
                        <td>
                            <?php if (!$cell['present']): ?>
                                <span class="missing-cell">— missing</span><span class="tag missing">missing</span>
                            <?php else: ?>
                                <span class="val <?php echo $c === $fb ? 'fallback-col' : 'other'; ?>"><?php echo htmlspecialchars($cell['value'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php if ($cell['untranslated']): ?><span class="tag untranslated">= <?php echo strtoupper($fb); ?></span><?php endif; ?>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
                <tr class="empty-row" id="empty-row" style="display: none;">
                    <td colspan="<?php echo count($codes) + 1; ?>">No keys match this filter.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="legend">
        <span><b>Missing</b>: key absent in this locale → falls back.</span>
        <span><b>Untranslated</b>: value identical to <?php echo strtoupper($fb); ?> (needs review).</span>
        <span><b>Orphan</b>: key not in <?php echo strtoupper($fb); ?> → renders raw key.</span>
        <span><b>{ } mismatch</b>: placeholders differ from <?php echo strtoupper($fb); ?>.</span>
    </div>
</div>

<div class="copied-toast" id="toast">Copied!</div>

<script>
    // --- Theme (shared convention with the Test Lab) ---
    const root = document.documentElement;
    const tBtn = document.getElementById('theme-toggle');
    const tIcon = document.getElementById('theme-icon');
    const tText = document.getElementById('theme-text');
    function applyTheme(t) {
        root.setAttribute('data-theme', t);
        tIcon.textContent = t === 'dark' ? '☀️' : '🌙';
        tText.textContent = t === 'dark' ? 'Light' : 'Dark';
    }
    applyTheme(localStorage.getItem('theme') || 'light');
    tBtn.addEventListener('click', () => {
        const next = (root.getAttribute('data-theme') || 'light') === 'light' ? 'dark' : 'light';
        localStorage.setItem('theme', next);
        applyTheme(next);
    });

    // --- Filtering: one active kind-filter + free-text search ---
    const rows = Array.from(document.querySelectorAll('#rows tr')).filter(r => r.id !== 'empty-row');
    const emptyRow = document.getElementById('empty-row');
    const search = document.getElementById('search');
    const chips = Array.from(document.querySelectorAll('.chip'));
    let activeFilter = 'all';

    function matchesFilter(row) {
        if (activeFilter === 'all') return true;
        const kinds = (row.dataset.kinds || '').split(' ').filter(Boolean);
        if (activeFilter === 'issues') return kinds.length > 0;
        return kinds.includes(activeFilter);
    }
    function applyFilters() {
        const q = search.value.trim().toLowerCase();
        let shown = 0;
        rows.forEach(row => {
            const ok = matchesFilter(row) && (q === '' || row.dataset.hay.includes(q));
            row.style.display = ok ? '' : 'none';
            if (ok) shown++;
        });
        emptyRow.style.display = shown === 0 ? '' : 'none';
    }
    chips.forEach(chip => chip.addEventListener('click', () => {
        activeFilter = chip.dataset.filter;
        chips.forEach(c => c.setAttribute('aria-pressed', c === chip ? 'true' : 'false'));
        applyFilters();
    }));
    search.addEventListener('input', applyFilters);

    // --- Click a key to copy it (eases jumping into the right locale file) ---
    const toast = document.getElementById('toast');
    let toastTimer = null;
    document.querySelectorAll('.key-cell').forEach(cell => {
        cell.addEventListener('click', () => {
            const key = cell.textContent.replace(/orphan|\{ \} mismatch/g, '').trim();
            navigator.clipboard?.writeText(key).then(() => {
                toast.textContent = 'Copied: ' + key;
                toast.classList.add('show');
                clearTimeout(toastTimer);
                toastTimer = setTimeout(() => toast.classList.remove('show'), 1400);
            });
        });
    });
</script>
</body>
</html>
