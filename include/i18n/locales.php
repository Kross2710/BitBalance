<?php
/**
 * Registry of available locales.
 *
 * To add a new language:
 *   1. Drop a new translation file at include/i18n/<code>.php that returns
 *      an associative array of keys → translated strings (see en.php).
 *   2. Add an entry below. `code` matches the filename (e.g. 'fr' → fr.php).
 *      `native` is the language name written in that language — used by the
 *      switcher so users can find their own language.
 *
 * Keep `en` as the first entry: it is the fallback locale and any key missing
 * from another file falls back to the English string.
 */
return [
    'en' => [
        'native' => 'English',
        'english' => 'English',
        'html_lang' => 'en',
    ],
    'vi' => [
        'native' => 'Tiếng Việt',
        'english' => 'Vietnamese',
        'html_lang' => 'vi',
    ],
];
