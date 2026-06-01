<?php
/**
 * tests/mascot_state_test.php
 *
 * CLI sanity harness for the PURE helpers in
 * include/handlers/mascot_state.php. No DB / no network required:
 *
 *     php tests/mascot_state_test.php
 *
 * Covers name sanitization (trim, whitespace collapse, control/markup
 * stripping, UTF-8-aware length cap) and the level → life-stage mapping.
 * The DB helpers (get/set name) are exercised by the live app, not here.
 */

require __DIR__ . '/../include/handlers/mascot_state.php';

$pass = 0; $fail = 0;

function check($label, $cond)
{
    global $pass, $fail;
    if ($cond) { $pass++; echo "  ok   - {$label}\n"; }
    else       { $fail++; echo "  FAIL - {$label}\n"; }
}

echo "== utf8 length / head ==\n";
check('len ascii', mascot_utf8_len('owly') === 4);
check('len vietnamese', mascot_utf8_len('Cú') === 2);          // C + ú (1 codepoint)
check('len emoji', mascot_utf8_len('🦉') === 1);
check('head ascii cut', mascot_utf8_head('abcdef', 3) === 'abc');
check('head no cut when short', mascot_utf8_head('abc', 10) === 'abc');
check('head keeps full multibyte char', mascot_utf8_head('áéí', 2) === 'áé');

echo "== sanitize: basic ==\n";
check('trims surrounding space', mascot_sanitize_name('   Owly   ') === 'Owly');
check('keeps inner emoji', mascot_sanitize_name('Owly 🦉') === 'Owly 🦉');
check('keeps vietnamese', mascot_sanitize_name('Cú Mèo') === 'Cú Mèo');
check('empty stays empty', mascot_sanitize_name('') === '');
check('whitespace-only -> empty', mascot_sanitize_name("   \t \n ") === '');

echo "== sanitize: whitespace collapse ==\n";
check('collapses inner runs', mascot_sanitize_name("Hoot    Owl") === 'Hoot Owl');
check('newlines become space', mascot_sanitize_name("Hoot\n\nOwl") === 'Hoot Owl');

echo "== sanitize: strips markup / control / injection chars ==\n";
check('strips angle brackets', mascot_sanitize_name('<b>Owl</b>') === 'bOwl/b');
check('strips braces + backtick', mascot_sanitize_name('Owl{`}') === 'Owl');
check('strips control byte', mascot_sanitize_name("Ow\x07ly") === 'Owly');

echo "== sanitize: length cap (UTF-8 aware) ==\n";
$max = mascot_name_max_len();
$long = str_repeat('a', $max + 10);
check('caps ascii to max', mascot_utf8_len(mascot_sanitize_name($long)) === $max);
$longVn = str_repeat('ú', $max + 5);
check('caps multibyte to max', mascot_utf8_len(mascot_sanitize_name($longVn)) === $max);

echo "== life stage from level ==\n";
check('level 0 -> egg', mascot_stage_from_level(0) === 'egg');
check('level 1 -> egg', mascot_stage_from_level(1) === 'egg');
check('level 2 -> baby', mascot_stage_from_level(2) === 'baby');
check('level 3 -> baby', mascot_stage_from_level(3) === 'baby');
check('level 4 -> adult', mascot_stage_from_level(4) === 'adult');
check('level 6 -> adult', mascot_stage_from_level(6) === 'adult');
check('level 7 -> sage', mascot_stage_from_level(7) === 'sage');
check('level 99 -> sage', mascot_stage_from_level(99) === 'sage');

echo "\n== summary ==\n";
echo "  {$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
