<?php
/**
 * tests/suites/I18nParityTest.php
 *
 * Guards the translation contract for include/i18n/<code>.php.
 *
 * Philosophy: assert only the invariants that are TRUE today, so this suite
 * stays green and turns RED the moment someone regresses it — e.g. adds an
 * English key without translating it, or breaks a {placeholder}. The softer,
 * judgement-call backlog (keys copied verbatim from English, or "orphan" keys
 * that exist only outside the fallback locale) is intentionally NOT failed here;
 * browse and manage those on the read-only viewer at tests/i18n.php, which is
 * powered by the same analyzer.
 */

require_once __DIR__ . '/../framework/I18nParity.php';

class I18nParityTest
{
    public $useDatabase = false; // Pure: just reads the locale files.

    /** @var array Cached analysis (rebuilt per instance — runner news up one per test). */
    private $report;

    public function setUp()
    {
        $this->report = (new I18nParity())->analyze();
    }

    /** The fallback locale must actually carry the strings everything else falls back to. */
    public function testFallbackLocaleHasKeys()
    {
        $fb = $this->report['fallback'];
        $count = $this->report['stats'][$fb]['total'];
        Assert::true($count > 0, "Fallback locale '{$fb}' has no keys — i18n would render raw keys everywhere.");
    }

    /** Every key in the fallback locale must exist in every other locale. */
    public function testEveryFallbackKeyIsTranslated()
    {
        $fb = $this->report['fallback'];
        $fbKeys = array_keys($this->report['tables'][$fb]);

        foreach ($this->report['codes'] as $code) {
            if ($code === $fb) {
                continue;
            }
            $missing = array();
            foreach ($fbKeys as $key) {
                if (!array_key_exists($key, $this->report['tables'][$code])) {
                    $missing[] = $key;
                }
            }
            Assert::equals(
                0,
                count($missing),
                "Locale '{$code}' is missing " . count($missing) . " key(s) present in '{$fb}': "
                    . $this->preview($missing)
            );
        }
    }

    /** Placeholders like {name} must match the fallback so i18n_interpolate() stays correct. */
    public function testPlaceholderParity()
    {
        $mm = $this->report['placeholderMismatches'];
        $lines = array();
        foreach (array_slice($mm, 0, 8) as $m) {
            $lines[] = $m['locale'] . ':' . $m['key']
                . ' [' . implode(',', $m['fallback']) . '] vs [' . implode(',', $m['value']) . ']';
        }
        Assert::equals(
            0,
            count($mm),
            count($mm) . " placeholder mismatch(es): " . implode(' | ', $lines)
        );
    }

    /** No translation should be present-but-empty (an empty string silently blanks the UI). */
    public function testNoEmptyTranslations()
    {
        $empties = array();
        foreach ($this->report['codes'] as $code) {
            foreach ($this->report['tables'][$code] as $key => $val) {
                if (trim((string) $val) === '') {
                    $empties[] = "{$code}:{$key}";
                }
            }
        }
        Assert::equals(
            0,
            count($empties),
            count($empties) . " empty translation value(s): " . $this->preview($empties)
        );
    }

    private function preview(array $items, $max = 6)
    {
        if (empty($items)) {
            return '(none)';
        }
        $head = array_slice($items, 0, $max);
        $more = count($items) - count($head);
        return implode(', ', $head) . ($more > 0 ? " … (+{$more} more)" : '');
    }
}
