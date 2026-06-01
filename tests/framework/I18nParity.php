<?php
/**
 * tests/framework/I18nParity.php
 *
 * Pure, dependency-free analyzer that compares the i18n translation tables
 * (include/i18n/<code>.php) against each other and surfaces the gaps the
 * runtime i18n layer silently hides:
 *
 *   • missing      — a key exists in another locale but not here, so t() falls
 *                    back (to English, or to the raw key) instead of translating.
 *   • untranslated — the value is byte-identical to the English string, i.e. it
 *                    was copied but never localized (needs a human's eyes).
 *   • orphan       — a key that is NOT in the fallback (en) locale at all, so
 *                    English/other locales render the raw key on screen.
 *   • placeholder  — the {tokens} in a translation don't match English, which
 *                    breaks i18n_interpolate() at runtime.
 *
 * No DB, no network, no session: it just `require`s the locale files (which only
 * `return` an array) and does set math. That makes it equally usable from the
 * CLI test suite (tests/suites/I18nParityTest.php) and the read-only viewer page
 * (tests/i18n.php). See include/i18n/i18n.php for the runtime side.
 */

class I18nParity
{
    /** @var string Directory holding locales.php + <code>.php tables. */
    private $dir;

    /** @var string Canonical/fallback locale: keys here are the translation contract. */
    private $fallback;

    public function __construct($i18nDir = null, $fallback = 'en')
    {
        // tests/framework -> project root -> include/i18n
        $this->dir = $i18nDir ?: dirname(__DIR__, 2) . '/include/i18n';
        $this->fallback = $fallback;
    }

    public function fallback()
    {
        return $this->fallback;
    }

    /** Locale registry (code => meta) from locales.php, fallback guaranteed first. */
    public function locales()
    {
        $path = $this->dir . '/locales.php';
        $reg = file_exists($path) ? require $path : array();
        if (!is_array($reg)) {
            $reg = array();
        }
        // Keep the fallback first so it reads as the left-most "source" column.
        if (isset($reg[$this->fallback])) {
            $fb = array($this->fallback => $reg[$this->fallback]);
            unset($reg[$this->fallback]);
            $reg = $fb + $reg;
        }
        return $reg;
    }

    /** Flattened key => string table for one locale (dot-joins any nested arrays). */
    public function table($code)
    {
        $path = $this->dir . '/' . $code . '.php';
        $raw = file_exists($path) ? require $path : array();
        if (!is_array($raw)) {
            $raw = array();
        }
        return $this->flatten($raw);
    }

    /** All locale tables, keyed by code, in registry order. */
    public function tables()
    {
        $out = array();
        foreach (array_keys($this->locales()) as $code) {
            $out[$code] = $this->table($code);
        }
        return $out;
    }

    private function flatten($arr, $prefix = '')
    {
        $out = array();
        foreach ($arr as $k => $v) {
            $key = $prefix === '' ? (string) $k : $prefix . '.' . $k;
            if (is_array($v)) {
                $out += $this->flatten($v, $key);
            } else {
                $out[$key] = (string) $v;
            }
        }
        return $out;
    }

    /** The {placeholder} tokens inside a string, sorted, for order-independent compare. */
    public static function placeholders($s)
    {
        if (!preg_match_all('/\{[a-zA-Z0-9_]+\}/', (string) $s, $m)) {
            return array();
        }
        $p = $m[0];
        sort($p);
        return $p;
    }

    /**
     * Full report. Shape:
     *   fallback   => 'en'
     *   locales    => [code => meta]                (registry order, fallback first)
     *   codes      => [code, ...]
     *   tables     => [code => [key => value]]
     *   keys       => [key, ...]                     (sorted union across all locales)
     *   rows       => [key => [
     *                    cells => [code => [present, value, untranslated]],
     *                    kinds => [ 'missing'|'untranslated'|'orphan'|'placeholder', ... ],
     *                 ]]
     *   stats      => [code => [total, missing, untranslated]]
     *   summary    => [unionKeys, fallbackKeys, orphanKeys, placeholderMismatches,
     *                  missingByLocale[code], untranslatedByLocale[code], rowsWithIssues]
     *   placeholderMismatches => [ [key, locale, fallback => [...], locale => [...]] ]
     */
    public function analyze()
    {
        $locales = $this->locales();
        $codes = array_keys($locales);
        $tables = $this->tables();
        $fb = $this->fallback;
        $fbTable = isset($tables[$fb]) ? $tables[$fb] : array();

        // Sorted union of every key seen in any locale.
        $union = array();
        foreach ($tables as $t) {
            foreach ($t as $k => $_) {
                $union[$k] = true;
            }
        }
        $keys = array_keys($union);
        sort($keys);

        $rows = array();
        $stats = array();
        $missingByLocale = array();
        $untranslatedByLocale = array();
        $placeholderMismatches = array();
        $orphanKeys = array();
        $rowsWithIssues = 0;

        foreach ($codes as $code) {
            $stats[$code] = array('total' => count($tables[$code]), 'missing' => 0, 'untranslated' => 0);
            $missingByLocale[$code] = array();
            $untranslatedByLocale[$code] = array();
        }

        foreach ($keys as $key) {
            $cells = array();
            $kinds = array();
            $inFallback = array_key_exists($key, $fbTable);
            $fbVal = $inFallback ? $fbTable[$key] : null;
            $fbPh = $inFallback ? self::placeholders($fbVal) : array();

            if (!$inFallback) {
                $kinds['orphan'] = true;
                $orphanKeys[] = $key;
            }

            foreach ($codes as $code) {
                $present = array_key_exists($key, $tables[$code]);
                $value = $present ? $tables[$code][$key] : null;
                $untranslated = false;

                if (!$present) {
                    $stats[$code]['missing']++;
                    $missingByLocale[$code][] = $key;
                    $kinds['missing'] = true;
                } else {
                    // untranslated = a non-fallback locale that copied English verbatim.
                    if ($code !== $fb && $inFallback && trim((string) $fbVal) !== '' && $value === $fbVal) {
                        $untranslated = true;
                        $stats[$code]['untranslated']++;
                        $untranslatedByLocale[$code][] = $key;
                        $kinds['untranslated'] = true;
                    }
                    // placeholder parity (only meaningful when both sides have the key).
                    if ($code !== $fb && $inFallback) {
                        $ph = self::placeholders($value);
                        if ($ph !== $fbPh) {
                            $kinds['placeholder'] = true;
                            $placeholderMismatches[] = array(
                                'key'      => $key,
                                'locale'   => $code,
                                'fallback' => $fbPh,
                                'value'    => $ph,
                            );
                        }
                    }
                }

                $cells[$code] = array(
                    'present'      => $present,
                    'value'        => $value,
                    'untranslated' => $untranslated,
                );
            }

            if (!empty($kinds)) {
                $rowsWithIssues++;
            }
            $rows[$key] = array('cells' => $cells, 'kinds' => array_keys($kinds));
        }

        return array(
            'fallback'  => $fb,
            'locales'   => $locales,
            'codes'     => $codes,
            'tables'    => $tables,
            'keys'      => $keys,
            'rows'      => $rows,
            'stats'     => $stats,
            'placeholderMismatches' => $placeholderMismatches,
            'summary'   => array(
                'unionKeys'             => count($keys),
                'fallbackKeys'          => count($fbTable),
                'orphanKeys'            => $orphanKeys,
                'missingByLocale'       => $missingByLocale,
                'untranslatedByLocale'  => $untranslatedByLocale,
                'placeholderMismatches' => count($placeholderMismatches),
                'rowsWithIssues'        => $rowsWithIssues,
            ),
        );
    }
}
