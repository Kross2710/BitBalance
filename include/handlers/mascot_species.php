<?php
/**
 * include/handlers/mascot_species.php
 *
 * Multi-pet registry for the mascot (P2 — "Pet Picker").
 *
 * Each species is its own character: own SVG art, own name (stored per-species
 * in mascot_pet_names — see mascot_state.php), own personality + per-state
 * flavor for the AI caption. Species is the collection axis; there are NO
 * life-stages (that idea was dropped — see docs/mascot-evolution-plan.md).
 *
 * This file is dependency-free pure PHP (no DB, no cURL): the catalog is data
 * and mascot_render_svg() returns a string, so it is CLI-testable
 * (tests/mascot_species_test.php). DB state (which species is active) lives in
 * mascot_state.php.
 *
 * Art architecture — shared scaffold + per-species body:
 *   • Shared CSS classes (drawn per-svg but styled once): .mascot-shadow,
 *     .health-aura, .mascot-eye-outer/inner, .mascot-pupil, .mascot-shine,
 *     .mascot-eyes-closed, .zzz-text — so the healthy aura and the overlimit
 *     "closed eyes + Zzz" behaviour work for every species for free.
 *   • Per-species: body/face/limb paths + the deficit prop.
 *
 * RMIT server rules: PHP 7.4 syntax only (no match/?->/named args); the prompt
 * strings carry UTF-8 (Vietnamese) but we never slice them here.
 */

if (!function_exists('mascot_species_catalog')) {

    /**
     * The species catalog. Order = display order in the picker.
     * `states` flavor mirrors the 4 nutrition vibe-states; the Body Positivity
     * Mandate (see BEATS.md) applies to every line.
     */
    function mascot_species_catalog()
    {
        return array(
            'owl' => array(
                'id'           => 'owl',
                'emoji'        => '🦉',
                'name_en'      => 'Blue Owl',
                'name_vi'      => 'chú Cú Xanh',
                'persona_en'   => 'cute, wise, and warm',
                'persona_vi'   => 'dễ thương, thông thái và ấm áp',
                'default_name' => 'Owly',
                'css'          => 'species-owl',
                'states'       => array(
                    'healthy' => array(
                        'en' => "The user is eating very healthily, keeping balanced nutrition, and is comfortably near or met their calorie goal. You feel energized, and a bright green aura is glowing around you. Praise their dedication and discipline!",
                        'vi' => "Người dùng đang ăn uống rất lành mạnh, đầy đủ dinh dưỡng và gần hoặc đạt mục tiêu calo hôm nay. Bạn đang cảm thấy tràn đầy năng lượng, có hào quang xanh (Green Aura) tỏa sáng rực rỡ xung quanh. Hãy khen ngợi sự kiên trì và kỷ luật thép của họ!",
                    ),
                    'overlimit' => array(
                        'en' => "The user has exceeded their daily calorie target today. You are full and sleepy (Zzz), resting in bed. [RULE]: DO NOT judge or make them feel bad! Gently suggest that rest and good recovery sleep are essential, encourage them to relax tonight, and remind them that tomorrow is a fresh new start!",
                        'vi' => "Người dùng đã ăn vượt quá calo mục tiêu hôm nay. Bạn đang ở trạng thái 'no nê và buồn ngủ' (Zzz), chuẩn bị ngủ khò. [QUY TẮC]: KHÔNG chỉ trích hay gây áp lực cho họ! Hãy khuyên họ nhẹ nhàng rằng cơ thể cần được nghỉ ngơi, giấc ngủ phục hồi là quan trọng nhất, hôm nay hãy thư giãn và ngày mai chúng ta sẽ lại cùng nhau cố gắng!",
                    ),
                    'deficit' => array(
                        'en' => "The user has logged calories but is significantly low on protein. You are wearing a sweatband and lifting tiny weights. Encourage them to add some high-quality protein (like chicken breast, eggs, or whey) so we can stay strong together!",
                        'vi' => "Người dùng nạp đủ calo nhưng lượng chất đạm (protein) lại bị thiếu nghiêm trọng. Bạn đang đeo băng trán thể thao và cố gắng nâng tạ mini. Hãy động viên họ nạp thêm một chút đạm chất lượng (như ức gà, trứng, sữa) để cơ bắp ta khỏe mạnh nhé!",
                    ),
                    'neutral' => array(
                        'en' => "The user hasn't logged any meals yet today. You are waiting for them to open their lunch box. Invite them to log their first bite of the day and start an awesome tracking journey together!",
                        'vi' => "Hôm nay người dùng chưa ghi nhận món ăn nào cả. Bạn đang đứng chờ nắp hộp cơm mở ra. Hãy rủ họ ghi nhận bữa ăn đầu tiên để bắt đầu một ngày tuyệt vời cùng nhau!",
                    ),
                ),
            ),
            'cat' => array(
                'id'           => 'cat',
                'emoji'        => '🐱',
                'name_en'      => 'Calico Cat',
                'name_vi'      => 'bé Mèo Tam Thể',
                'persona_en'   => 'cozy, a touch sassy, and secretly devoted',
                'persona_vi'   => 'ấm áp, hơi chảnh nhưng rất thương bạn',
                'default_name' => 'Mochi',
                'css'          => 'species-cat',
                'states'       => array(
                    'healthy' => array(
                        'en' => "The user is eating beautifully and is near or met their goal with good balance. You are purring happily with a warm glow around you. Praise their balance with cozy, contented pride!",
                        'vi' => "Người dùng ăn uống cân bằng và đang gần hoặc đã chạm mục tiêu. Bạn đang kêu 'gừ gừ' mãn nguyện, có hào quang ấm áp tỏa quanh mình. Hãy khen sự cân bằng của họ một cách dễ thương, đầy tự hào!",
                    ),
                    'overlimit' => array(
                        'en' => "The user went a little over today. You are curled into a cozy loaf, dozing off (Zzz). [RULE]: NEVER judge them! Gently say that a good nap and a fresh tomorrow are all we need.",
                        'vi' => "Hôm nay người dùng nạp hơi quá một chút. Bạn đang cuộn tròn như 'ổ bánh mì' và lim dim ngủ (Zzz). [QUY TẮC]: TUYỆT ĐỐI không phán xét! Hãy nhẹ nhàng nói rằng một giấc ngủ ngon và ngày mai tươi mới là đủ rồi.",
                    ),
                    'deficit' => array(
                        'en' => "The user has logged calories but is low on protein. You are eyeing a tasty fish, dreaming of protein. Encourage them to add quality protein (fish, eggs, chicken) so we both stay strong!",
                        'vi' => "Người dùng nạp đủ calo nhưng lại thiếu chất đạm. Bạn đang nhìn chằm chằm một con cá ngon lành, mơ về protein. Hãy động viên họ thêm đạm chất lượng (cá, trứng, ức gà) để cả hai cùng khỏe nhé!",
                    ),
                    'neutral' => array(
                        'en' => "The user hasn't logged any meals yet today. You are sitting patiently with your tail curled, waiting by the food bowl. Invite them to log their first bite together!",
                        'vi' => "Hôm nay người dùng chưa ghi nhận bữa nào. Bạn đang ngồi ngoan, cuộn đuôi, chờ bên chén thức ăn. Hãy rủ họ ghi nhận miếng đầu tiên cùng nhau nhé!",
                    ),
                ),
            ),
        );
    }

    /** List of species ids in display order. */
    function mascot_species_ids()
    {
        return array_keys(mascot_species_catalog());
    }

    /** Is $id a known species? */
    function mascot_species_valid($id)
    {
        $cat = mascot_species_catalog();
        return is_string($id) && isset($cat[$id]);
    }

    /** Return a species entry, falling back to the owl for unknown ids. */
    function mascot_species_get($id)
    {
        $cat = mascot_species_catalog();
        if (is_string($id) && isset($cat[$id])) {
            return $cat[$id];
        }
        return $cat['owl'];
    }

    /** Localized state-flavor text for a species + vibe state. */
    function mascot_species_state_text($id, $state, $lang)
    {
        $entry = mascot_species_get($id);
        $lang = ($lang === 'vi') ? 'vi' : 'en';
        if (!isset($entry['states'][$state])) {
            $state = 'neutral';
        }
        return $entry['states'][$state][$lang];
    }

    /** Render one species' inline SVG. Active species is visible; others hidden. */
    function mascot_render_svg($id, $isActive)
    {
        $id = mascot_species_valid($id) ? $id : 'owl';
        $entry = mascot_species_get($id);
        $hidden = $isActive ? '' : ' hidden';
        $cssClass = $entry['css'];
        $open = '<svg viewBox="0 0 200 200" class="mascot-svg ' . $cssClass . '" id="mascotSvg-' . $id . '" data-species="' . $id . '"' . $hidden . '>';
        $body = ($id === 'cat') ? mascot_svg_cat_body() : mascot_svg_owl_body();
        return $open . $body . '</svg>';
    }

    /** Owl body — the original mascot art (unchanged), on the shared scaffold. */
    function mascot_svg_owl_body()
    {
        return <<<SVG

            <ellipse cx="100" cy="165" rx="55" ry="12" class="mascot-shadow" />
            <circle cx="100" cy="100" r="75" class="health-aura" />

            <path d="M75 160 Q80 170 85 162 T95 160" class="mascot-feet" />
            <path d="M105 160 Q115 170 120 162 T125 160" class="mascot-feet" />

            <path d="M45 100 Q15 90 35 130 T52 115" class="mascot-wing left-wing" />
            <path d="M155 100 Q185 90 165 130 T148 115" class="mascot-wing right-wing" />

            <path d="M50 80 C50 40, 150 40, 150 80 C150 130, 50 130, 50 80 Z" class="mascot-body-outer" />
            <path d="M65 95 C65 75, 135 75, 135 95 C135 130, 65 130, 65 95 Z" class="mascot-belly" />
            <path d="M75 110 L85 115 L95 110 M105 110 L115 115 L125 110" class="mascot-belly-feathers" />

            <circle cx="78" cy="78" r="22" class="mascot-eye-outer" />
            <circle cx="122" cy="78" r="22" class="mascot-eye-outer" />
            <circle cx="78" cy="78" r="16" class="mascot-eye-inner" />
            <circle cx="122" cy="78" r="16" class="mascot-eye-inner" />
            <circle cx="78" cy="78" r="9" class="mascot-pupil left-pupil" />
            <circle cx="122" cy="78" r="9" class="mascot-pupil right-pupil" />
            <circle cx="81" cy="74" r="3.5" class="mascot-shine" />
            <circle cx="125" cy="74" r="3.5" class="mascot-shine" />

            <path d="M62 78 Q78 94 94 78" class="mascot-eyes-closed left-closed" />
            <path d="M106 78 Q122 94 138 78" class="mascot-eyes-closed right-closed" />

            <polygon points="94,86 106,86 100,98" class="mascot-beak" />

            <g class="mascot-sweatband-group">
                <rect x="52" y="44" width="96" height="12" rx="4" class="mascot-sweatband" />
                <rect x="90" y="44" width="20" height="12" class="mascot-sweatband-stripe" />
            </g>
            <g class="mascot-dumbbell left-dumbbell">
                <rect x="15" y="110" width="10" height="24" rx="2" class="db-plate" />
                <rect x="23" y="120" width="16" height="4" class="db-bar" />
                <rect x="37" y="110" width="10" height="24" rx="2" class="db-plate" />
            </g>
            <g class="mascot-dumbbell right-dumbbell">
                <rect x="153" y="110" width="10" height="24" rx="2" class="db-plate" />
                <rect x="161" y="120" width="16" height="4" class="db-bar" />
                <rect x="175" y="110" width="10" height="24" rx="2" class="db-plate" />
            </g>

            <g class="mascot-zzz-group">
                <text x="145" y="55" class="zzz-text zzz-1">Z</text>
                <text x="160" y="40" class="zzz-text zzz-2">z</text>
                <text x="172" y="28" class="zzz-text zzz-3">z</text>
            </g>

SVG;
    }

    /**
     * Cat body — a chibi calico. Reuses the shared eye/aura/zzz/shadow classes
     * (so healthy-aura + overlimit-closed-eyes work for free); cat-specific
     * parts use .cat-* classes. Deficit shows a fish (the protein metaphor).
     * Eye centres sit at (78,82)/(122,82) so the shared closed-eye arcs align.
     */
    function mascot_svg_cat_body()
    {
        return <<<SVG

            <ellipse cx="100" cy="166" rx="52" ry="11" class="mascot-shadow" />
            <circle cx="100" cy="100" r="75" class="health-aura" />

            <!-- Tail -->
            <path d="M148 150 Q186 142 176 104 Q172 88 158 96" class="cat-tail" />

            <!-- Body -->
            <path d="M58 152 C58 112, 142 112, 142 152 C142 172, 58 172, 58 152 Z" class="cat-body" />

            <!-- Ears -->
            <polygon points="56,58 72,16 98,52" class="cat-ear" />
            <polygon points="144,58 128,16 102,52" class="cat-ear" />
            <polygon points="64,52 73,30 86,50" class="cat-ear-inner" />
            <polygon points="136,52 127,30 114,50" class="cat-ear-inner" />

            <!-- Head -->
            <circle cx="100" cy="86" r="52" class="cat-head" />
            <!-- Calico cheek patches -->
            <path d="M52 92 Q60 60 86 62 Q70 84 74 110 Q60 108 52 92 Z" class="cat-patch" />
            <path d="M148 92 Q140 60 114 62 Q130 84 126 110 Q140 108 148 92 Z" class="cat-patch" />

            <!-- Eyes (shared classes; centres at y=82) -->
            <circle cx="78" cy="82" r="18" class="mascot-eye-outer" />
            <circle cx="122" cy="82" r="18" class="mascot-eye-outer" />
            <circle cx="78" cy="82" r="13" class="mascot-eye-inner" />
            <circle cx="122" cy="82" r="13" class="mascot-eye-inner" />
            <circle cx="78" cy="82" r="7" class="mascot-pupil" />
            <circle cx="122" cy="82" r="7" class="mascot-pupil" />
            <circle cx="81" cy="79" r="3" class="mascot-shine" />
            <circle cx="125" cy="79" r="3" class="mascot-shine" />

            <path d="M64 82 Q78 96 92 82" class="mascot-eyes-closed" />
            <path d="M108 82 Q122 96 136 82" class="mascot-eyes-closed" />

            <!-- Nose + mouth -->
            <polygon points="94,99 106,99 100,107" class="cat-nose" />
            <path d="M100 107 Q92 116 83 110 M100 107 Q108 116 117 110" class="cat-mouth" />

            <!-- Whiskers -->
            <path d="M46 98 L74 102 M44 108 L74 110" class="cat-whisker" />
            <path d="M154 98 L126 102 M156 108 L126 110" class="cat-whisker" />

            <!-- Deficit prop: a fish (protein!) -->
            <g class="cat-fish-group">
                <ellipse cx="42" cy="150" rx="19" ry="9" class="cat-fish-body" />
                <polygon points="24,150 8,141 8,159" class="cat-fish-tail" />
                <circle cx="50" cy="147" r="2.4" class="cat-fish-eye" />
            </g>

            <!-- Zzz (shared) -->
            <g class="mascot-zzz-group">
                <text x="145" y="55" class="zzz-text zzz-1">Z</text>
                <text x="160" y="40" class="zzz-text zzz-2">z</text>
                <text x="172" y="28" class="zzz-text zzz-3">z</text>
            </g>

SVG;
    }
}
