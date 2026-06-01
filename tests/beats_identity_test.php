<?php
/**
 * tests/beats_identity_test.php
 *
 * CLI sanity harness for the pure scoring engine in
 * include/handlers/beats_identity.php. No DB / no network required:
 *
 *     php tests/beats_identity_test.php
 *
 * Verifies the math behaves directionally (energetic music + energetic food
 * scores high; opposite profiles score low; archetypes land where expected).
 */

require __DIR__ . '/../include/handlers/beats_identity.php';

$pass = 0; $fail = 0;

function check($label, $cond)
{
    global $pass, $fail;
    if ($cond) { $pass++; echo "  ok   - {$label}\n"; }
    else       { $fail++; echo "  FAIL - {$label}\n"; }
}

function approx($a, $b, $eps = 0.0001) { return abs($a - $b) <= $eps; }

echo "== clamp ==\n";
check('clamp upper', bb_beats_clamp01(1.7) === 1.0);
check('clamp lower', bb_beats_clamp01(-3) === 0.0);
check('clamp mid', approx(bb_beats_clamp01(0.42), 0.42));

echo "== genre axes ==\n";
$edm = bb_beats_genre_axes('melodic dubstep');
check('edm high energy', $edm['energy'] >= 0.8);
$lofi = bb_beats_genre_axes('lo-fi beats');
check('lofi low energy', $lofi['energy'] <= 0.35);
check('lofi high comfort', $lofi['comfort'] >= 0.8);
check('lofi nocturnal', $lofi['nocturnal'] >= 0.7);
$unknown = bb_beats_genre_axes('zzz-unmatched-genre');
check('unknown is neutral energy', approx($unknown['energy'], 0.5));

echo "== music fingerprint ==\n";
$hypeMusic = bb_beats_music_axes(array('edm' => 5, 'hardstyle' => 3, 'techno' => 2), 70, 3);
check('hype music energetic', $hypeMusic['energy'] >= 0.8);
check('hype music low comfort', $hypeMusic['comfort'] <= 0.35);

$chillMusic = bb_beats_music_axes(array('lo-fi' => 6, 'ambient' => 2, 'acoustic jazz' => 2), 30, 3);
check('chill music low energy', $chillMusic['energy'] <= 0.35);
check('chill music high comfort', $chillMusic['comfort'] >= 0.8);
check('chill music nocturnal', $chillMusic['nocturnal'] >= 0.65);

$diverseMusic = bb_beats_music_axes(
    array('edm'=>1,'jazz'=>1,'k-pop'=>1,'metal'=>1,'lo-fi'=>1,'rap'=>1,'classical'=>1,'folk'=>1,'house'=>1,'soul'=>1),
    20, 10);
check('many genres + niche → high diversity', $diverseMusic['diversity'] >= 0.7);
$narrowMusic = bb_beats_music_axes(array('pop' => 10), 95, 1);
check('one mainstream genre → low diversity', $narrowMusic['diversity'] <= 0.3);

echo "== food fingerprint ==\n";
check('too few logs → null', bb_beats_food_axes_from_rows(array(array('food_item'=>'x','calories'=>100))) === null);

// High-protein, daytime, repetitive eater (gym fuel)
$rowsHype = array();
for ($i = 0; $i < 10; $i++) {
    $rowsHype[] = array('food_item'=>'chicken breast','protein'=>40,'carbs'=>5,'fat'=>4,'calories'=>250,'hour'=>13);
}
$foodHype = bb_beats_food_axes_from_rows($rowsHype);
check('protein eater → high energy', $foodHype['energy'] >= 0.55);
check('single food → low diversity', $foodHype['diversity'] <= 0.1);
check('daytime → low nocturnal', $foodHype['nocturnal'] <= 0.05);
check('top food detected', $foodHype['top_food'] === 'chicken breast');

// Carb/fat heavy, late-night, varied comfort eater
$rowsCozy = array(
    array('food_item'=>'ramen','protein'=>10,'carbs'=>60,'fat'=>20,'calories'=>600,'hour'=>23),
    array('food_item'=>'ice cream','protein'=>4,'carbs'=>40,'fat'=>18,'calories'=>350,'hour'=>22),
    array('food_item'=>'pizza','protein'=>15,'carbs'=>50,'fat'=>22,'calories'=>700,'hour'=>1),
    array('food_item'=>'cookies','protein'=>3,'carbs'=>45,'fat'=>15,'calories'=>300,'hour'=>23),
    array('food_item'=>'pho','protein'=>20,'carbs'=>55,'fat'=>10,'calories'=>500,'hour'=>21),
    array('food_item'=>'chocolate','protein'=>5,'carbs'=>50,'fat'=>25,'calories'=>400,'hour'=>0),
);
$foodCozy = bb_beats_food_axes_from_rows($rowsCozy);
check('carb/fat eater → high comfort', $foodCozy['comfort'] >= 0.5);
check('late eater → high nocturnal', $foodCozy['nocturnal'] >= 0.8);
check('varied foods → decent diversity', $foodCozy['diversity'] >= 0.7);

echo "== congruence ==\n";
$congAligned = bb_beats_congruence($hypeMusic, $foodHype);
$congOpposite = bb_beats_congruence($chillMusic, $foodHype);
echo "  (aligned score = {$congAligned['score']}, opposite score = {$congOpposite['score']})\n";
check('aligned > opposite', $congAligned['score'] > $congOpposite['score']);
check('score in 0..100', $congAligned['score'] >= 0 && $congAligned['score'] <= 100);
check('per-axis has agreement', isset($congAligned['per_axis']['energy']['agreement']));

// Perfectly identical vectors → 100
$identical = bb_beats_congruence(
    array('energy'=>0.5,'comfort'=>0.5,'diversity'=>0.5,'nocturnal'=>0.5),
    array('energy'=>0.5,'comfort'=>0.5,'diversity'=>0.5,'nocturnal'=>0.5));
check('identical → 100', $identical['score'] === 100);

echo "== archetype assignment ==\n";
$archHype = bb_beats_assign_archetype(bb_beats_combine($hypeMusic, $foodHype), 'en');
echo "  (hype combined → {$archHype['name']} {$archHype['emoji']})\n";
check('energetic profile → energetic archetype',
    in_array($archHype['key'], array('sprinter', 'hype', 'strategist'), true));

$archCozy = bb_beats_assign_archetype(bb_beats_combine($chillMusic, $foodCozy), 'vi');
echo "  (cozy combined → {$archCozy['name']} {$archCozy['emoji']})\n";
check('cozy nocturnal profile → cozy/romantic/dreamer',
    in_array($archCozy['key'], array('romantic', 'cozy', 'dreamer', 'minimalist'), true));
check('vi name non-empty', $archCozy['name'] !== '');

echo "== fuel suggestions (deterministic, no AI) ==\n";
$fuelHype = bb_beats_fuel_suggestions(array('energy'=>0.95,'comfort'=>0.3,'diversity'=>0.6,'nocturnal'=>0.4,'top_genre'=>'edm'), null, 'en');
$moodsHype = array_map(function ($s) { return $s['mood']; }, $fuelHype);
check('returns exactly 3', count($fuelHype) === 3);
check('distinct moods', count(array_unique($moodsHype)) === 3);
check('hype → energetic suggested', in_array('energetic', $moodsHype, true));
check('each has food + kcal', $fuelHype[0]['food'] !== '' && $fuelHype[0]['kcal'] > 0);
check('genre woven into reason', strpos($fuelHype[0]['reason'], 'edm') !== false);

$fuelCozy = bb_beats_fuel_suggestions(array('energy'=>0.2,'comfort'=>0.9,'diversity'=>0.3,'nocturnal'=>0.85,'top_genre'=>'lo-fi'), null, 'vi');
$moodsCozy = array_map(function ($s) { return $s['mood']; }, $fuelCozy);
check('cozy late → sad or chill suggested', in_array('sad', $moodsCozy, true) || in_array('chill', $moodsCozy, true));
check('vi labels non-empty', $fuelCozy[0]['vibe'] !== '');

$fuelLowBudget = bb_beats_fuel_suggestions(array('energy'=>0.95,'comfort'=>0.3,'diversity'=>0.6,'nocturnal'=>0.4), 120, 'en');
$moodsLow = array_map(function ($s) { return $s['mood']; }, $fuelLowBudget);
check('low budget → lighter moods favoured (focus/happy/chill present)',
    in_array('focus', $moodsLow, true) || in_array('happy', $moodsLow, true) || in_array('chill', $moodsLow, true));
check('low budget caps kcal', max(array_map(function ($s) { return $s['kcal']; }, $fuelLowBudget)) <= 220);

echo "== last.fm tag filter ==\n";
$sampleTags = array(
    array('name' => 'indie pop', 'count' => 100),
    array('name' => 'seen live', 'count' => 80),      // folksonomy noise → dropped
    array('name' => 'lo-fi', 'count' => 45),
    array('name' => 'chill', 'count' => 30),
    array('name' => 'obscure', 'count' => 3),         // below minCount → dropped
);
$filtered = bb_beats_filter_lastfm_tags($sampleTags, 5, 10);
check('drops sub-threshold tags', !in_array('obscure', $filtered, true));
check('drops noise tags', !in_array('seen live', $filtered, true));
check('keeps strong genre tags', in_array('indie pop', $filtered, true) && in_array('lo-fi', $filtered, true));
check('respects max count', count(bb_beats_filter_lastfm_tags($sampleTags, 2, 10)) === 2);
check('empty/garbage input → empty', bb_beats_filter_lastfm_tags(null) === array() && bb_beats_filter_lastfm_tags('x') === array());

// Filtered Last.fm tags should drive bb_beats_music_axes just like Spotify genres would.
$gw = array();
foreach ($filtered as $g) { $gw[$g] = isset($gw[$g]) ? $gw[$g] + 1 : 1; }
$axesFromTags = bb_beats_music_axes($gw, 50, count($gw));
check('lo-fi/chill tags → high comfort axis', $axesFromTags['comfort'] >= 0.6);

echo "== genre label & noise filtering in axes ==\n";
$lbl = bb_beats_music_axes(array('pop' => 10, 'synth-funk' => 3), 50, 2);
check('label prefers specific over generic', $lbl['top_genre'] === 'synth-funk');
$allGeneric = bb_beats_music_axes(array('pop' => 10), 50, 1);
check('all-generic → still labels (fallback)', $allGeneric['top_genre'] === 'pop');
$noisy = bb_beats_music_axes(array('lo-fi' => 5, 'seen live' => 10), 50, 2);
check('noise excluded from axes (comfort stays high)', $noisy['comfort'] >= 0.8);
check('noise never chosen as label', $noisy['top_genre'] === 'lo-fi');

echo "== archetype rarity & lookup ==\n";
check('lookup by key works', bb_beats_archetype_by_key('romantic')['emoji'] === '🌙');
check('unknown key → null', bb_beats_archetype_by_key('nope') === null);
check('centre archetype → common', bb_beats_archetype_rarity('maestro') === 'common');
check('extreme archetype → legendary', bb_beats_archetype_rarity('romantic') === 'legendary');
check('hype → epic', bb_beats_archetype_rarity('hype') === 'epic');
check('minimalist → rare', bb_beats_archetype_rarity('minimalist') === 'rare');
check('unknown key → common (safe default)', bb_beats_archetype_rarity('nope') === 'common');
$tiers = array();
foreach (bb_beats_archetype_catalog() as $a) { $t = bb_beats_archetype_rarity($a['key']); $tiers[$t] = ($tiers[$t] ?? 0) + 1; }
echo "  (rarity spread: " . json_encode($tiers) . ")\n";
check('every tier represented', isset($tiers['common'], $tiers['rare'], $tiers['epic'], $tiers['legendary']));

echo "== single-food axes (DJ Mixer) ==\n";
$proteinFood = bb_beats_food_axes_single(40, 5, 4, 250, 13);
check('protein food → energy-leaning', $proteinFood['energy'] >= 0.5);
check('daytime → low nocturnal', $proteinFood['nocturnal'] < 0.5);
$lateCarbFood = bb_beats_food_axes_single(8, 60, 20, 600, 23);
check('carb/fat → comfort-leaning', $lateCarbFood['comfort'] >= 0.5);
check('late hour → high nocturnal', $lateCarbFood['nocturnal'] >= 0.8);
// A pair lands on a sensible catalog archetype.
$pairArch = bb_beats_assign_archetype(bb_beats_combine(
    array('energy'=>0.2,'comfort'=>0.9,'diversity'=>0.4,'nocturnal'=>0.85), $lateCarbFood), 'en');
check('chill+late+carb pair → cozy/romantic/dreamer',
    in_array($pairArch['key'], array('romantic','cozy','dreamer','minimalist'), true));

echo "\n== RESULT: {$pass} passed, {$fail} failed ==\n";
exit($fail > 0 ? 1 : 0);
