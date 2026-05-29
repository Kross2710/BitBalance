<?php
/**
 * Lightweight i18n layer for BitBalance.
 *
 * Public surface:
 *   - t($key, $vars = [])      Translate + HTML-escape. Use this for text in HTML.
 *   - t_raw($key, $vars = [])  Translate WITHOUT escaping. Caller is responsible
 *                              for context-appropriate escaping (e.g. JSON, attr).
 *   - current_locale()         Returns the active locale code (e.g. 'en').
 *   - available_locales()      Returns the locales registry (see locales.php).
 *   - set_locale($code, $pdo, $userId)  Persist a new locale choice.
 *   - resolve_locale($pdo, $user)       Decide which locale to use this request.
 *
 * Resolution order (highest priority first):
 *   1. Logged-in user's userStatus.language_preference column
 *   2. Cookie `lang`
 *   3. Accept-Language header (best match against the registry)
 *   4. Fallback to 'en'
 *
 * Translation files live at include/i18n/<code>.php and return a flat
 * associative array. Missing keys fall back to English; if even English
 * lacks the key, t() returns the key itself so missing translations are
 * obvious in the UI.
 */

if (!defined('I18N_FALLBACK_LOCALE')) {
    define('I18N_FALLBACK_LOCALE', 'en');
}

/** @var array<string,array<string,string>> Loaded translation tables, keyed by locale. */
$GLOBALS['__i18n_tables'] = [];
/** @var string Active locale for this request. */
$GLOBALS['__i18n_locale'] = I18N_FALLBACK_LOCALE;

function available_locales(): array
{
    static $locales = null;
    if ($locales === null) {
        $locales = require __DIR__ . '/locales.php';
    }
    return $locales;
}

function is_valid_locale(string $code): bool
{
    return isset(available_locales()[$code]);
}

function current_locale(): string
{
    return $GLOBALS['__i18n_locale'];
}

function load_locale_table(string $code): array
{
    if (isset($GLOBALS['__i18n_tables'][$code])) {
        return $GLOBALS['__i18n_tables'][$code];
    }
    $path = __DIR__ . '/' . $code . '.php';
    $table = file_exists($path) ? require $path : [];
    if (!is_array($table)) {
        $table = [];
    }
    $GLOBALS['__i18n_tables'][$code] = $table;
    return $table;
}

/**
 * Interpolate {placeholders} in a translated string. Values are inserted as-is
 * — callers using t() get HTML escaping applied to the whole result, which
 * also escapes interpolated values. t_raw() callers must escape themselves.
 */
function i18n_interpolate(string $msg, array $vars): string
{
    if (empty($vars)) {
        return $msg;
    }
    $keys = array_map(fn($k) => '{' . $k . '}', array_keys($vars));
    return str_replace($keys, array_values($vars), $msg);
}

function t_raw(string $key, array $vars = []): string
{
    $locale = current_locale();
    $table = load_locale_table($locale);
    if (array_key_exists($key, $table)) {
        return i18n_interpolate($table[$key], $vars);
    }
    if ($locale !== I18N_FALLBACK_LOCALE) {
        $fallback = load_locale_table(I18N_FALLBACK_LOCALE);
        if (array_key_exists($key, $fallback)) {
            return i18n_interpolate($fallback[$key], $vars);
        }
    }
    return $key;
}

function t(string $key, array $vars = []): string
{
    return htmlspecialchars(t_raw($key, $vars), ENT_QUOTES, 'UTF-8');
}

function negotiate_accept_language(string $header): ?string
{
    if ($header === '') {
        return null;
    }
    $available = array_keys(available_locales());
    $parts = explode(',', $header);
    $candidates = [];
    foreach ($parts as $i => $part) {
        $segments = explode(';', trim($part));
        $tag = strtolower(trim($segments[0]));
        if ($tag === '') continue;
        $q = 1.0;
        for ($j = 1; $j < count($segments); $j++) {
            $seg = trim($segments[$j]);
            if (str_starts_with($seg, 'q=')) {
                $q = (float) substr($seg, 2);
            }
        }
        // Tiebreak earlier entries above later ones at equal q.
        $candidates[] = ['tag' => $tag, 'q' => $q, 'order' => $i];
    }
    usort($candidates, function ($a, $b) {
        if ($a['q'] === $b['q']) return $a['order'] <=> $b['order'];
        return $b['q'] <=> $a['q'];
    });
    foreach ($candidates as $cand) {
        $tag = $cand['tag'];
        // Exact match (e.g. 'vi' or 'en')
        if (in_array($tag, $available, true)) {
            return $tag;
        }
        // Prefix match (e.g. 'vi-VN' → 'vi', 'en-US' → 'en')
        $prefix = explode('-', $tag)[0];
        if (in_array($prefix, $available, true)) {
            return $prefix;
        }
    }
    return null;
}

function resolve_locale(?PDO $pdo, ?array $user): string
{
    // 1. Logged-in user's stored preference.
    if ($pdo && $user && !empty($user['user_id'])) {
        try {
            $stmt = $pdo->prepare('SELECT language_preference FROM userStatus WHERE user_id = :uid');
            $stmt->execute([':uid' => (int) $user['user_id']]);
            $pref = $stmt->fetchColumn();
            if ($pref && is_valid_locale($pref)) {
                return $pref;
            }
        } catch (Throwable $e) {
            // Migration may not have run yet — fall through silently.
        }
    }
    // 2. Cookie.
    if (!empty($_COOKIE['lang']) && is_valid_locale($_COOKIE['lang'])) {
        return $_COOKIE['lang'];
    }
    // 3. Accept-Language header.
    if (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $negotiated = negotiate_accept_language($_SERVER['HTTP_ACCEPT_LANGUAGE']);
        if ($negotiated !== null) {
            return $negotiated;
        }
    }
    return I18N_FALLBACK_LOCALE;
}

function set_locale(string $code, ?PDO $pdo = null, ?int $userId = null): bool
{
    if (!is_valid_locale($code)) {
        return false;
    }
    $GLOBALS['__i18n_locale'] = $code;
    // 30-day cookie so guests stay on their pick across visits.
    $secure = (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] == 1))
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    setcookie('lang', $code, [
        'expires' => time() + 60 * 60 * 24 * 30,
        'path' => '/',
        'secure' => $secure,
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
    if (isset($_SESSION['user'])) {
        $_SESSION['user']['language_preference'] = $code;
    }
    if ($pdo && $userId) {
        try {
            $stmt = $pdo->prepare('UPDATE userStatus SET language_preference = :lang WHERE user_id = :uid');
            $stmt->execute([':lang' => $code, ':uid' => $userId]);
        } catch (Throwable $e) {
            error_log('set_locale DB update failed: ' . $e->getMessage());
        }
    }
    return true;
}

function apply_locale(string $code): void
{
    $GLOBALS['__i18n_locale'] = is_valid_locale($code) ? $code : I18N_FALLBACK_LOCALE;
}

function html_lang_attr(): string
{
    $locales = available_locales();
    $code = current_locale();
    return $locales[$code]['html_lang'] ?? $code;
}
