<?php
/**
 * tests/mascot_species_test.php
 *
 * CLI sanity harness for the pure species registry + SVG renderer in
 * include/handlers/mascot_species.php. No DB / no network:
 *
 *     php tests/mascot_species_test.php
 */

require __DIR__ . '/../include/handlers/mascot_species.php';

$pass = 0; $fail = 0;
function check($label, $cond)
{
    global $pass, $fail;
    if ($cond) { $pass++; echo "  ok   - {$label}\n"; }
    else       { $fail++; echo "  FAIL - {$label}\n"; }
}

echo "== catalog ==\n";
$ids = mascot_species_ids();
check('owl present', in_array('owl', $ids, true));
check('cat present', in_array('cat', $ids, true));
check('owl is first (default)', $ids[0] === 'owl');

echo "== validity ==\n";
check('owl valid', mascot_species_valid('owl') === true);
check('cat valid', mascot_species_valid('cat') === true);
check('garbage invalid', mascot_species_valid('dragon') === false);
check('non-string invalid', mascot_species_valid(null) === false);

echo "== get + fallback ==\n";
check('get cat returns cat', mascot_species_get('cat')['id'] === 'cat');
check('get unknown falls back to owl', mascot_species_get('zzz')['id'] === 'owl');
check('owl has emoji', mascot_species_get('owl')['emoji'] === '🦉');
check('cat has default name', mascot_species_get('cat')['default_name'] === 'Mochi');

echo "== state flavor text (non-empty, both langs, all states) ==\n";
$states = array('healthy', 'overlimit', 'deficit', 'neutral');
foreach (array('owl', 'cat') as $sp) {
    foreach ($states as $st) {
        check("{$sp}/{$st} en non-empty", mascot_species_state_text($sp, $st, 'en') !== '');
        check("{$sp}/{$st} vi non-empty", mascot_species_state_text($sp, $st, 'vi') !== '');
    }
}
check('unknown state falls back to neutral', mascot_species_state_text('owl', 'bogus', 'en') === mascot_species_state_text('owl', 'neutral', 'en'));

echo "== SVG renderer ==\n";
$owlActive = mascot_render_svg('owl', true);
$owlHidden = mascot_render_svg('owl', false);
$catActive = mascot_render_svg('cat', true);
check('owl svg has species-owl class', strpos($owlActive, 'species-owl') !== false);
check('owl svg has data-species', strpos($owlActive, 'data-species="owl"') !== false);
check('active svg NOT hidden', strpos($owlActive, ' hidden>') === false);
check('inactive svg IS hidden', strpos($owlHidden, ' hidden>') !== false);
check('cat svg has species-cat class', strpos($catActive, 'species-cat') !== false);
check('cat svg has the fish (deficit prop)', strpos($catActive, 'cat-fish-group') !== false);
check('cat reuses shared eye class', strpos($catActive, 'mascot-eye-outer') !== false);
check('cat reuses shared zzz class', strpos($catActive, 'zzz-text') !== false);
check('cat reuses shared aura class', strpos($catActive, 'health-aura') !== false);
check('unknown species renders as owl', strpos(mascot_render_svg('zzz', true), 'species-owl') !== false);
check('both svgs balanced <svg></svg>', substr_count($catActive, '<svg') === 1 && substr_count($catActive, '</svg>') === 1);

echo "\n== summary ==\n";
echo "  {$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
