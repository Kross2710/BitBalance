<?php
/**
 * include/handlers/beats_identity.php
 *
 * "The Mirror" personality engine for Diet & Beats (Direction 1).
 *
 * Builds two behavioural fingerprints — music taste and eating habits — and
 * projects BOTH onto the same shared axes so they can be compared:
 *
 *     energy     — high-tempo / high-protein-density "fuel"
 *     comfort    — soothing / carb-and-fat warmth
 *     diversity  — eclectic vs routine (variety of genres / foods)
 *     nocturnal  — night-owl tendency (late genres / late meals)
 *
 * The whole file is intentionally DEPENDENCY-FREE pure PHP (no DB, no cURL) for
 * the math, so it can be unit-tested from the CLI (see tests/beats_identity_test.php).
 * The one DB helper, bb_beats_food_fingerprint(), is a thin wrapper that queries
 * intakeLog then delegates to the pure bb_beats_food_axes_from_rows().
 *
 * RMIT server rules honoured: PHP 7.4 syntax only (no match/str_contains/?->),
 * no mbstring (genre strings are ASCII → strtolower is safe here).
 */

if (!function_exists('bb_beats_clamp01')) {

    /** Clamp any number into the 0..1 range. */
    function bb_beats_clamp01($x)
    {
        $x = (float) $x;
        if ($x < 0) return 0.0;
        if ($x > 1) return 1.0;
        return $x;
    }

    /** The shared axes, in display order. */
    function bb_beats_axes()
    {
        return array('energy', 'comfort', 'diversity', 'nocturnal');
    }

    /** Per-axis weights for the Congruence score (nocturnal is a softer signal on the music side). */
    function bb_beats_congruence_weights()
    {
        return array('energy' => 1.0, 'comfort' => 1.0, 'diversity' => 0.8, 'nocturnal' => 0.5);
    }

    /**
     * Map one Spotify genre string to its (energy, comfort, nocturnal) contribution.
     * Granular Spotify genres ("melodic dubstep", "bedroom pop"…) are matched by
     * keyword. Unmatched genres fall back to a neutral midpoint.
     */
    function bb_beats_genre_axes($genre)
    {
        $g = strtolower(trim((string) $genre));
        // Each bucket: list of substrings → [energy, comfort, nocturnal]
        $buckets = array(
            // High-energy electronic / heavy
            array(array('edm', 'techno', 'house', 'trance', 'hardstyle', 'phonk', 'dubstep',
                        'drum and bass', 'dnb', 'electro', 'rave', 'big room', 'hardcore',
                        'hyperpop', 'breakcore'),
                  array(0.95, 0.20, 0.55)),
            // Rock / metal / punk
            array(array('rock', 'metal', 'punk', 'grunge', 'emo rock', 'metalcore'),
                  array(0.88, 0.30, 0.50)),
            // Hip-hop / rap / trap / drill
            array(array('hip hop', 'hip-hop', 'rap', 'trap', 'drill'),
                  array(0.70, 0.45, 0.55)),
            // Pop / k-pop / dance-pop / latin (upbeat, mainstream-ish)
            array(array('pop', 'k-pop', 'kpop', 'j-pop', 'v-pop', 'vpop', 'disco', 'funk',
                        'afrobeat', 'reggaeton', 'latin', 'dance pop'),
                  array(0.65, 0.55, 0.35)),
            // Lo-fi / chill / ambient / sleep (calm + nocturnal)
            array(array('lo-fi', 'lofi', 'chill', 'ambient', 'sleep', 'bedroom', 'downtempo',
                        'chillhop', 'study', 'mellow'),
                  array(0.25, 0.90, 0.78)),
            // Acoustic / jazz / soul / r&b (warm comfort)
            array(array('acoustic', 'jazz', 'soul', 'r&b', 'rnb', 'neo soul', 'soft', 'singer-songwriter'),
                  array(0.30, 0.85, 0.62)),
            // Sad / emotional / folk / slowcore
            array(array('sad', 'emo', 'ballad', 'indie folk', 'folk', 'slowcore', 'melancholy', 'blues'),
                  array(0.30, 0.80, 0.60)),
            // Classical / instrumental / score
            array(array('classical', 'piano', 'instrumental', 'orchestral', 'film score', 'soundtrack'),
                  array(0.30, 0.85, 0.60)),
        );

        $hits = array();
        foreach ($buckets as $bucket) {
            $matched = false;
            foreach ($bucket[0] as $needle) {
                if ($g !== '' && strpos($g, $needle) !== false) {
                    $matched = true;
                    break;
                }
            }
            if ($matched) {
                $hits[] = $bucket[1];
            }
        }

        if (empty($hits)) {
            return array('energy' => 0.5, 'comfort' => 0.5, 'nocturnal' => 0.35);
        }

        $e = 0.0; $c = 0.0; $n = 0.0;
        foreach ($hits as $h) {
            $e += $h[0]; $c += $h[1]; $n += $h[2];
        }
        $k = count($hits);
        return array('energy' => $e / $k, 'comfort' => $c / $k, 'nocturnal' => $n / $k);
    }

    /**
     * Build the MUSIC fingerprint from genre weights + catalog signals.
     *
     * @param array $genreWeights  [genre_string => weight] (weight = #artists/plays carrying it)
     * @param float $avgPopularity 0..100 average Spotify artist popularity (mainstream-ness)
     * @param int   $distinctGenres number of distinct genres seen
     * @return array{energy:float,comfort:float,diversity:float,nocturnal:float,top_genre:string}
     */
    function bb_beats_music_axes($genreWeights, $avgPopularity = 50.0, $distinctGenres = 0)
    {
        $e = 0.0; $c = 0.0; $n = 0.0; $wSum = 0.0;
        $topGenre = ''; $topW = -1.0;

        foreach ($genreWeights as $genre => $w) {
            $w = (float) $w;
            if ($w <= 0) continue;
            $ax = bb_beats_genre_axes($genre);
            $e += $ax['energy'] * $w;
            $c += $ax['comfort'] * $w;
            $n += $ax['nocturnal'] * $w;
            $wSum += $w;
            if ($w > $topW) { $topW = $w; $topGenre = (string) $genre; }
        }

        if ($wSum <= 0) {
            // No genre data → neutral fingerprint (still usable for congruence).
            $e = 0.5; $c = 0.5; $n = 0.35;
        } else {
            $e /= $wSum; $c /= $wSum; $n /= $wSum;
        }

        if ($distinctGenres <= 0) {
            $distinctGenres = count(array_filter($genreWeights, function ($w) { return (float) $w > 0; }));
        }
        // Diversity = genre spread blended with niche-ness (low popularity = more adventurous).
        $spread = bb_beats_clamp01($distinctGenres / 10.0);
        $niche  = bb_beats_clamp01(1.0 - ((float) $avgPopularity / 100.0));
        $diversity = bb_beats_clamp01($spread * 0.6 + $niche * 0.4);

        return array(
            'energy'    => bb_beats_clamp01($e),
            'comfort'   => bb_beats_clamp01($c),
            'diversity' => $diversity,
            'nocturnal' => bb_beats_clamp01($n),
            'top_genre' => $topGenre,
        );
    }

    /**
     * Build the FOOD fingerprint from already-fetched intake rows (pure → testable).
     * Each row: protein, carbs, fat, calories (numeric), hour (0-23), food_item (string).
     *
     * @return array|null  axes + meta, or null when there are too few logs to be stable.
     */
    function bb_beats_food_axes_from_rows($rows, $minLogs = 6)
    {
        $rows = is_array($rows) ? $rows : array();
        $total = count($rows);
        if ($total < $minLogs) {
            return null;
        }

        $sumP = 0.0; $sumC = 0.0; $sumF = 0.0; $sumKcal = 0.0; $late = 0;
        $freq = array();

        foreach ($rows as $r) {
            $sumP += (float) ($r['protein'] ?? 0);
            $sumC += (float) ($r['carbs'] ?? 0);
            $sumF += (float) ($r['fat'] ?? 0);
            $sumKcal += (float) ($r['calories'] ?? 0);

            $hour = isset($r['hour']) ? (int) $r['hour'] : -1;
            if ($hour >= 21 || ($hour >= 0 && $hour <= 4)) {
                $late++;
            }

            $name = strtolower(trim((string) ($r['food_item'] ?? '')));
            if ($name !== '') {
                if (!isset($freq[$name])) $freq[$name] = 0;
                $freq[$name]++;
            }
        }

        $macroSum = $sumP + $sumC + $sumF;
        if ($macroSum <= 0) $macroSum = 1.0;
        $proteinRatio = $sumP / $macroSum;
        $carbRatio    = $sumC / $macroSum;
        $fatRatio     = $sumF / $macroSum;
        $avgKcal      = $sumKcal / $total;
        $calBand      = bb_beats_clamp01($avgKcal / 800.0);

        $energy  = bb_beats_clamp01($proteinRatio * 0.6 + $calBand * 0.4);
        $comfort = bb_beats_clamp01($carbRatio * 0.5 + $fatRatio * 0.3 + $calBand * 0.2);

        // Diversity via 1 - Herfindahl concentration over food frequencies.
        $hhi = 0.0;
        $named = array_sum($freq);
        if ($named > 0) {
            foreach ($freq as $cnt) {
                $share = $cnt / $named;
                $hhi += $share * $share;
            }
        } else {
            $hhi = 1.0;
        }
        $diversity = bb_beats_clamp01(1.0 - $hhi);

        $nocturnal = bb_beats_clamp01($late / $total);

        // Top food (most frequently logged).
        $topFood = ''; $topCnt = -1;
        foreach ($freq as $name => $cnt) {
            if ($cnt > $topCnt) { $topCnt = $cnt; $topFood = $name; }
        }

        return array(
            'energy'        => $energy,
            'comfort'       => $comfort,
            'diversity'     => $diversity,
            'nocturnal'     => $nocturnal,
            'top_food'      => $topFood,
            'total_logs'    => $total,
            'distinct_foods'=> count($freq),
            'avg_kcal'      => (int) round($avgKcal),
            'macro_g'       => array(
                'protein' => (int) round($sumP),
                'carbs'   => (int) round($sumC),
                'fat'     => (int) round($sumF),
            ),
        );
    }

    /**
     * DB wrapper: pull the user's recent intake then delegate to the pure function.
     * Returns null on insufficient data or query failure (caller shows a "forming" state).
     */
    function bb_beats_food_fingerprint(PDO $pdo, $userId, $days = 30, $minLogs = 6)
    {
        try {
            $days = max(1, min(120, (int) $days));
            $stmt = $pdo->prepare(
                "SELECT food_item, calories, protein, carbs, fat, meal_category,
                        HOUR(date_intake) AS hour
                 FROM intakeLog
                 WHERE user_id = ? AND date_intake >= DATE_SUB(NOW(), INTERVAL {$days} DAY)"
            );
            $stmt->execute(array((int) $userId));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
        } catch (PDOException $e) {
            return null;
        }
        return bb_beats_food_axes_from_rows($rows, $minLogs);
    }

    /**
     * Congruence: how aligned the music and food fingerprints are, 0..100.
     * Uses a weighted L1 agreement (1 - |m-f| per axis), which is directly
     * interpretable: "on average your two sides agree X%".
     *
     * @return array{score:int, per_axis: array<string,array{music:float,food:float,agreement:int}>}
     */
    function bb_beats_congruence($music, $food)
    {
        $weights = bb_beats_congruence_weights();
        $perAxis = array();
        $acc = 0.0; $wSum = 0.0;

        foreach (bb_beats_axes() as $axis) {
            $m = bb_beats_clamp01($music[$axis] ?? 0.5);
            $f = bb_beats_clamp01($food[$axis] ?? 0.5);
            $agree = 1.0 - abs($m - $f);
            $w = $weights[$axis] ?? 1.0;
            $acc += $agree * $w;
            $wSum += $w;
            $perAxis[$axis] = array(
                'music'     => (int) round($m * 100),
                'food'      => (int) round($f * 100),
                'agreement' => (int) round($agree * 100),
            );
        }

        $score = $wSum > 0 ? (int) round(($acc / $wSum) * 100) : 0;
        return array('score' => $score, 'per_axis' => $perAxis);
    }

    /**
     * Hand-designed archetype catalog. Each centroid lives in the shared-axis space;
     * a user is assigned to the nearest one. Names are stable & finite → collectible.
     */
    function bb_beats_archetype_catalog()
    {
        return array(
            array('key' => 'sprinter',
                  'emoji' => '🏋️',
                  'name' => array('vi' => 'Vận Động Viên Kỷ Luật', 'en' => 'The Disciplined Sprinter'),
                  'axes' => array('energy' => 0.85, 'comfort' => 0.25, 'diversity' => 0.30, 'nocturnal' => 0.20),
                  'voice' => array('vi' => 'năng lượng cao, kỷ luật, ăn sạch nghe bốc',
                                   'en' => 'high-energy, disciplined, clean eats & hype beats')),
            array('key' => 'romantic',
                  'emoji' => '🌙',
                  'name' => array('vi' => 'Kẻ Lãng Mạn Nửa Đêm', 'en' => 'The Midnight Romantic'),
                  'axes' => array('energy' => 0.20, 'comfort' => 0.85, 'diversity' => 0.40, 'nocturnal' => 0.90),
                  'voice' => array('vi' => 'cú đêm, nhạc dịu, đồ ăn an ủi',
                                   'en' => 'night owl, soft tunes, comfort food')),
            array('key' => 'cozy',
                  'emoji' => '🧸',
                  'name' => array('vi' => 'Tín Đồ An Yên', 'en' => 'The Cozy Creature of Habit'),
                  'axes' => array('energy' => 0.30, 'comfort' => 0.80, 'diversity' => 0.20, 'nocturnal' => 0.40),
                  'voice' => array('vi' => 'an yên, ổn định, gu quen thuộc dễ chịu',
                                   'en' => 'cozy, steady, comforting routines')),
            array('key' => 'explorer',
                  'emoji' => '🌀',
                  'name' => array('vi' => 'Nhà Thám Hiểm Đa Sắc', 'en' => 'The Eclectic Explorer'),
                  'axes' => array('energy' => 0.55, 'comfort' => 0.50, 'diversity' => 0.95, 'nocturnal' => 0.50),
                  'voice' => array('vi' => 'phiêu lưu, đa dạng, không ngại thử cái mới',
                                   'en' => 'adventurous, varied, never the same twice')),
            array('key' => 'hype',
                  'emoji' => '🔥',
                  'name' => array('vi' => 'Cỗ Máy Bùng Nổ', 'en' => 'The Hype Machine'),
                  'axes' => array('energy' => 0.95, 'comfort' => 0.35, 'diversity' => 0.60, 'nocturnal' => 0.55),
                  'voice' => array('vi' => 'bốc lửa, dồn dập, sống hết công suất',
                                   'en' => 'explosive, relentless, full throttle')),
            array('key' => 'minimalist',
                  'emoji' => '🍃',
                  'name' => array('vi' => 'Người Tối Giản Thư Thái', 'en' => 'The Mellow Minimalist'),
                  'axes' => array('energy' => 0.35, 'comfort' => 0.70, 'diversity' => 0.25, 'nocturnal' => 0.45),
                  'voice' => array('vi' => 'nhẹ nhàng, tối giản, ít mà chất',
                                   'en' => 'calm, minimal, less but better')),
            array('key' => 'maestro',
                  'emoji' => '🎯',
                  'name' => array('vi' => 'Nhạc Trưởng Cân Bằng', 'en' => 'The Balanced Maestro'),
                  'axes' => array('energy' => 0.50, 'comfort' => 0.50, 'diversity' => 0.50, 'nocturnal' => 0.45),
                  'voice' => array('vi' => 'cân bằng mọi mặt, hài hoà',
                                   'en' => 'balanced across the board, in harmony')),
            array('key' => 'dreamer',
                  'emoji' => '🍰',
                  'name' => array('vi' => 'Mộng Mơ Hảo Ngọt', 'en' => 'The Sweet-Tooth Dreamer'),
                  'axes' => array('energy' => 0.40, 'comfort' => 0.90, 'diversity' => 0.55, 'nocturnal' => 0.60),
                  'voice' => array('vi' => 'mơ mộng, hảo ngọt, chiều bản thân',
                                   'en' => 'dreamy, sweet-toothed, treats first')),
            array('key' => 'snacker',
                  'emoji' => '⚡',
                  'name' => array('vi' => 'Thợ Săn Bữa Phụ', 'en' => 'The Power-Snacker'),
                  'axes' => array('energy' => 0.70, 'comfort' => 0.60, 'diversity' => 0.70, 'nocturnal' => 0.65),
                  'voice' => array('vi' => 'năng động, ăn vặt thông minh, bắt trend',
                                   'en' => 'snappy, smart-snacking, trend-riding')),
            array('key' => 'strategist',
                  'emoji' => '♟️',
                  'name' => array('vi' => 'Chiến Lược Gia Bền Bỉ', 'en' => 'The Steady Strategist'),
                  'axes' => array('energy' => 0.60, 'comfort' => 0.40, 'diversity' => 0.35, 'nocturnal' => 0.30),
                  'voice' => array('vi' => 'bền bỉ, có kế hoạch, kiên định',
                                   'en' => 'steady, planned, consistent')),
        );
    }

    /**
     * Assign the nearest archetype to a combined fingerprint (Euclidean over the 4 axes).
     *
     * @return array{key:string,emoji:string,name:string,voice:string,distance:float}
     */
    function bb_beats_assign_archetype($combined, $lang = 'en')
    {
        $lang = ($lang === 'vi') ? 'vi' : 'en';
        $best = null; $bestDist = INF;

        foreach (bb_beats_archetype_catalog() as $arch) {
            $d = 0.0;
            foreach (bb_beats_axes() as $axis) {
                $diff = bb_beats_clamp01($combined[$axis] ?? 0.5) - $arch['axes'][$axis];
                $d += $diff * $diff;
            }
            $d = sqrt($d);
            if ($d < $bestDist) {
                $bestDist = $d;
                $best = $arch;
            }
        }

        if ($best === null) {
            return array('key' => '', 'emoji' => '🎵', 'name' => '', 'voice' => '', 'distance' => INF);
        }
        return array(
            'key'      => $best['key'],
            'emoji'    => $best['emoji'],
            'name'     => $best['name'][$lang],
            'voice'    => $best['voice'][$lang],
            'distance' => round($bestDist, 4),
        );
    }

    /** Combine two fingerprints into one personality location (per-axis mean). */
    function bb_beats_combine($music, $food)
    {
        $out = array();
        foreach (bb_beats_axes() as $axis) {
            $m = bb_beats_clamp01($music[$axis] ?? 0.5);
            $f = bb_beats_clamp01($food[$axis] ?? 0.5);
            $out[$axis] = ($m + $f) / 2.0;
        }
        return $out;
    }

    /**
     * Reduce a Last.fm artist.getTopTags response to a short list of clean genre tags.
     * Last.fm returns tags sorted by count (0-100) desc; keep the strongest few that clear
     * a minimum weight so folksonomy noise ("seen live", "favourites") is dropped. The
     * survivors feed straight into bb_beats_music_axes() via genre keyword matching.
     *
     * @param array $toptags decoded ['toptags']['tag'] array of { name, count }
     * @return array lowercased genre strings, most popular first
     */
    function bb_beats_filter_lastfm_tags($toptags, $max = 5, $minCount = 10)
    {
        $out = array();
        if (!is_array($toptags)) {
            return $out;
        }
        foreach ($toptags as $t) {
            if (!is_array($t)) continue;
            $name = strtolower(trim((string) ($t['name'] ?? '')));
            $count = (int) ($t['count'] ?? 0);
            if ($name === '' || $count < (int) $minCount) continue;
            $out[] = $name;
            if (count($out) >= (int) $max) break;
        }
        return $out;
    }

    /**
     * Deterministic "fuel" suggestions seeded by the MUSIC fingerprint + remaining
     * calorie budget. Replaces the old per-request Gemini call in beats_fuel.php:
     * the music vibe is already known from the fingerprint, so suggestions become a
     * stable rule-map (zero AI cost) — see docs/ai-cost-optimization.md.
     *
     * @param array      $musicAxes     energy/comfort/diversity/nocturnal (+ optional top_genre)
     * @param int|null   $remainingKcal today's remaining budget, or null if no goal set
     * @return array  up to 3 { mood, vibe, food, reason, kcal }
     */
    function bb_beats_fuel_suggestions($musicAxes, $remainingKcal = null, $lang = 'en')
    {
        $lang = ($lang === 'vi') ? 'vi' : 'en';
        $energy  = bb_beats_clamp01($musicAxes['energy'] ?? 0.5);
        $comfort = bb_beats_clamp01($musicAxes['comfort'] ?? 0.5);
        $div     = bb_beats_clamp01($musicAxes['diversity'] ?? 0.5);
        $noct    = bb_beats_clamp01($musicAxes['nocturnal'] ?? 0.5);
        $genre   = trim((string) ($musicAxes['top_genre'] ?? ''));

        // Score each mood from the music profile; pick the 3 strongest.
        $scores = array(
            'energetic' => $energy,
            'happy'     => $energy * 0.5 + $div * 0.5,
            'chill'     => $comfort * (1.0 - $noct),
            'sad'       => $comfort * $noct,
            'focus'     => (1.0 - $energy) * 0.5 + (1.0 - $div) * 0.5,
        );

        // Low remaining budget → favour the lighter moods.
        if ($remainingKcal !== null && $remainingKcal < 250) {
            $scores['energetic'] *= 0.6;
            $scores['sad'] *= 0.7;
            $scores['focus'] += 0.15;
            $scores['happy'] += 0.15;
        }

        arsort($scores);
        $picks = array_slice(array_keys($scores), 0, 3);

        $catalog = array(
            'energetic' => array('kcal' => 220,
                'vi' => array('vibe' => 'Nạp Năng Lượng', 'food' => 'Sinh tố whey protein'),
                'en' => array('vibe' => 'High-Energy Fuel', 'food' => 'Whey protein shake')),
            'happy' => array('kcal' => 160,
                'vi' => array('vibe' => 'Vui Vẻ Rực Rỡ', 'food' => 'Tô trái cây nhiều màu'),
                'en' => array('vibe' => 'Feel-Good Bites', 'food' => 'A colorful fruit bowl')),
            'chill' => array('kcal' => 180,
                'vi' => array('vibe' => 'Lo-Fi Thư Giãn', 'food' => 'Trà xanh ấm & bánh quy yến mạch'),
                'en' => array('vibe' => 'Chill Lo-Fi', 'food' => 'Warm green tea & oatmeal cookies')),
            'sad' => array('kcal' => 250,
                'vi' => array('vibe' => 'Ấm Lòng', 'food' => 'Tô súp ấm hoặc chocolate đắng'),
                'en' => array('vibe' => 'Cozy Comfort', 'food' => 'A warm bowl of soup or dark chocolate')),
            'focus' => array('kcal' => 200,
                'vi' => array('vibe' => 'Tập Trung Sâu', 'food' => 'Hạt hỗn hợp & một quả chuối'),
                'en' => array('vibe' => 'Deep Focus', 'food' => 'Mixed nuts & a banana')),
        );

        $genreBit = $genre !== '' ? ('"' . $genre . '"') : ($lang === 'vi' ? 'gu nhạc của bạn' : 'your taste');
        $reasons = array(
            'energetic' => $lang === 'vi' ? "Hợp với chất bốc của {$genreBit}." : "Matches the high energy of {$genreBit}.",
            'happy'     => $lang === 'vi' ? "Tươi sáng đúng vibe {$genreBit}." : "Bright and cheerful, just like {$genreBit}.",
            'chill'     => $lang === 'vi' ? "Nhẹ nhàng theo nhịp {$genreBit}." : "Easy and mellow, in tune with {$genreBit}.",
            'sad'       => $lang === 'vi' ? "Xoa dịu cảm xúc cùng {$genreBit}." : "Soothing comfort for {$genreBit} nights.",
            'focus'     => $lang === 'vi' ? "Giữ tỉnh táo khi đắm trong {$genreBit}." : "Keeps you sharp while {$genreBit} plays.",
        );

        $out = array();
        foreach ($picks as $mood) {
            $c = $catalog[$mood];
            $kcal = (int) $c['kcal'];
            if ($remainingKcal !== null && $remainingKcal > 0 && $kcal > $remainingKcal) {
                $kcal = max(80, (int) $remainingKcal);
            }
            $out[] = array(
                'mood'   => $mood,
                'vibe'   => $c[$lang]['vibe'],
                'food'   => $c[$lang]['food'],
                'reason' => $reasons[$mood],
                'kcal'   => $kcal,
            );
        }
        return $out;
    }
}
