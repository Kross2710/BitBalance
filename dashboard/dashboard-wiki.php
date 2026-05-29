<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../include/init.php';
require_once __DIR__ . '/../include/handlers/log_attempt.php';

if ($isLoggedIn) {
    log_attempt($pdo, $user['user_id'], 'view', 'User ' . $user['user_id'] . ' clicked on nutrition wiki', 'dashboard', null);
}

$activePage = 'wiki';
$activeHeader = 'dashboard';
$bodyClass = 'page-wiki';
$displayUser = $isLoggedIn ? $user['user_name'] : 'Guest';

// --- Static wiki content ---
$wikiCategories = [
    'basics' => ['label' => 'Basics',         'icon' => 'fa-book-open'],
    'macros' => ['label' => 'Macronutrients', 'icon' => 'fa-drumstick-bite'],
    'micros' => ['label' => 'Micronutrients', 'icon' => 'fa-pills'],
    'habits' => ['label' => 'Healthy Habits', 'icon' => 'fa-heart'],
];

$wikiArticles = [
    [
        'cat' => 'basics', 'icon' => 'fa-fire', 'mins' => '2 min',
        'title' => 'What Is a Calorie?',
        'summary' => 'The unit of energy behind every food you eat.',
        'body' => "<p>A calorie is a unit of energy. The numbers on food labels are technically <strong>kilocalories</strong> — the fuel your body pulls from food to power everything from breathing to sprinting.</p><ul><li><strong>Energy in</strong> — the food and drinks you consume.</li><li><strong>Energy out</strong> — survival functions, digestion, and movement.</li><li>Body weight follows the gap between the two, averaged over weeks.</li></ul><p class='wiki-tip'><i class='fas fa-lightbulb'></i> Judge progress by weekly trends. Daily readings swing with water, salt, and meal timing.</p>",
    ],
    [
        'cat' => 'basics', 'icon' => 'fa-scale-balanced', 'mins' => '3 min',
        'title' => 'Energy Balance Explained',
        'summary' => 'Why deficit, surplus, and maintenance decide your weight.',
        'body' => "<p>Energy balance is the relationship between the calories you eat and the calories you burn. It decides whether you lose, gain, or maintain weight.</p><ul><li><strong>Deficit</strong> — eating less than you burn; the body taps stored energy and you lose weight.</li><li><strong>Surplus</strong> — eating more than you burn; the extra is stored and you gain weight.</li><li><strong>Maintenance</strong> — intake matches output; weight holds steady.</li></ul><p>A modest deficit of 300-500 kcal per day is sustainable for most people and protects muscle and energy levels.</p><p class='wiki-tip'><i class='fas fa-lightbulb'></i> Aggressive deficits backfire — they spike hunger and are hard to keep up.</p>",
    ],
    [
        'cat' => 'basics', 'icon' => 'fa-gauge-high', 'mins' => '3 min',
        'title' => 'BMR & TDEE: Your Burn Rate',
        'summary' => 'The two numbers behind how much you burn each day.',
        'body' => "<p>Your body burns calories even at complete rest. Two numbers help you set a realistic goal.</p><ul><li><strong>BMR (Basal Metabolic Rate)</strong> — energy to keep you alive at rest: heartbeat, breathing, organ function. It is the largest slice of your burn.</li><li><strong>TDEE (Total Daily Energy Expenditure)</strong> — your BMR plus digestion, daily activity, and exercise. This is your true maintenance level.</li></ul><p>To lose weight, eat below your TDEE; to gain, eat above it.</p><p class='wiki-tip'><i class='fas fa-lightbulb'></i> Use the Calculator page to estimate your TDEE from age, weight, height, and activity.</p>",
    ],
    [
        'cat' => 'basics', 'icon' => 'fa-bullseye', 'mins' => '3 min',
        'title' => 'Setting a Calorie Goal That Sticks',
        'summary' => 'How to pick a target you can actually sustain.',
        'body' => "<p>A good calorie goal is one you can actually live with. Start from your TDEE, then adjust for your objective.</p><ul><li><strong>Fat loss</strong> — subtract 300-500 kcal for roughly 0.25-0.5 kg per week.</li><li><strong>Maintenance</strong> — eat around your TDEE.</li><li><strong>Muscle gain</strong> — add 150-300 kcal alongside resistance training.</li></ul><p>Review every 2-3 weeks. If the scale and your energy are not moving as expected, nudge the goal — do not slash it.</p><p class='wiki-tip'><i class='fas fa-lightbulb'></i> The best goal is the one you can repeat on a normal, busy week.</p>",
    ],
    [
        'cat' => 'macros', 'icon' => 'fa-drumstick-bite', 'mins' => '3 min',
        'title' => 'Protein: The Building Block',
        'summary' => 'Why protein keeps you full and protects muscle.',
        'body' => "<p>Protein builds and repairs muscle, skin, enzymes, and hormones. It is also the most filling macronutrient, which makes it a powerful ally for appetite control.</p><ul><li>Provides <strong>4 kcal per gram</strong>.</li><li>A common target is <strong>1.6-2.2 g per kg of body weight</strong> when active.</li><li>Strong sources: chicken, fish, eggs, dairy, tofu, lentils, and beans.</li></ul><p>Spreading protein across meals supports muscle better than one large serving.</p><p class='wiki-tip'><i class='fas fa-lightbulb'></i> Anchor every meal with a protein source first, then build around it.</p>",
    ],
    [
        'cat' => 'macros', 'icon' => 'fa-bread-slice', 'mins' => '3 min',
        'title' => 'Carbohydrates: Your Main Fuel',
        'summary' => "Your body's main fuel — and how to choose it well.",
        'body' => "<p>Carbohydrates are your body's preferred fuel, especially for the brain and intense exercise. They are not the enemy — quality is what matters.</p><ul><li>Provides <strong>4 kcal per gram</strong>.</li><li><strong>Complex carbs</strong> (oats, brown rice, vegetables, legumes) digest slowly and keep energy steady.</li><li><strong>Simple carbs</strong> (sweets, soda, white bread) spike energy fast and fade quickly.</li></ul><p>Pairing carbs with protein or fiber softens blood-sugar swings.</p><p class='wiki-tip'><i class='fas fa-lightbulb'></i> You do not need zero carbs — you need better carbs, most of the time.</p>",
    ],
    [
        'cat' => 'macros', 'icon' => 'fa-cheese', 'mins' => '3 min',
        'title' => 'Dietary Fat: Not the Enemy',
        'summary' => 'Essential, energy-dense, and easy to over-eat.',
        'body' => "<p>Fat supports hormone production, brain health, and the absorption of vitamins A, D, E, and K. It is essential — just energy-dense.</p><ul><li>Provides <strong>9 kcal per gram</strong>, more than double protein or carbs.</li><li><strong>Unsaturated fats</strong> (olive oil, nuts, avocado, fatty fish) support heart health.</li><li><strong>Limit trans fats</strong> and heavily processed fried foods.</li></ul><p>Because it is calorie-dense, fat is easy to over-eat — measure oils and nut butters.</p><p class='wiki-tip'><i class='fas fa-lightbulb'></i> A tablespoon of oil is about 120 kcal. Small amounts add up fast.</p>",
    ],
    [
        'cat' => 'macros', 'icon' => 'fa-seedling', 'mins' => '2 min',
        'title' => 'Fiber: The Underrated Macro',
        'summary' => 'The indigestible carb that keeps you full and regular.',
        'body' => "<p>Fiber is a carbohydrate your body cannot digest — and that is exactly why it is valuable. It feeds gut bacteria, slows digestion, and keeps you full.</p><ul><li>Aim for <strong>25-38 g per day</strong>; most people fall short.</li><li>Found in vegetables, fruit, whole grains, beans, nuts, and seeds.</li><li>Helps steady blood sugar and supports healthy digestion.</li></ul><p class='wiki-tip'><i class='fas fa-lightbulb'></i> Increase fiber gradually and drink more water to stay comfortable.</p>",
    ],
    [
        'cat' => 'micros', 'icon' => 'fa-pills', 'mins' => '3 min',
        'title' => 'Vitamins 101',
        'summary' => 'Water-soluble vs fat-soluble, and how to get enough.',
        'body' => "<p>Vitamins are micronutrients your body needs in small amounts to function — they release no energy themselves but enable countless processes.</p><ul><li><strong>Water-soluble</strong> (B vitamins, vitamin C) are not stored well, so eat them regularly.</li><li><strong>Fat-soluble</strong> (A, D, E, K) are stored in body fat and absorbed alongside dietary fat.</li><li>A varied, colorful diet covers most needs without supplements.</li></ul><p class='wiki-tip'><i class='fas fa-lightbulb'></i> Eat the rainbow — different colors of produce signal different vitamins.</p>",
    ],
    [
        'cat' => 'micros', 'icon' => 'fa-gem', 'mins' => '3 min',
        'title' => 'Essential Minerals',
        'summary' => 'Calcium, iron, and the minerals that keep you running.',
        'body' => "<p>Minerals are inorganic nutrients that support bones, fluid balance, oxygen transport, and nerve signaling.</p><ul><li><strong>Calcium</strong> — bone strength; dairy, leafy greens, fortified foods.</li><li><strong>Iron</strong> — carries oxygen in blood; red meat, beans, spinach.</li><li><strong>Potassium &amp; sodium</strong> — fluid balance and blood pressure; whole foods favor potassium.</li><li><strong>Magnesium &amp; zinc</strong> — energy, immunity, and recovery.</li></ul><p class='wiki-tip'><i class='fas fa-lightbulb'></i> Pair plant-based iron with vitamin C (beans + peppers) to boost absorption.</p>",
    ],
    [
        'cat' => 'micros', 'icon' => 'fa-droplet', 'mins' => '2 min',
        'title' => 'Hydration & Electrolytes',
        'summary' => 'How much water you need and why electrolytes matter.',
        'body' => "<p>Water carries nutrients, regulates temperature, and supports nearly every reaction in the body. Even mild dehydration dents focus and energy.</p><ul><li>A practical target is <strong>around 2-3 litres a day</strong>, more with heat or exercise.</li><li>Electrolytes — sodium, potassium, magnesium — keep fluid balanced.</li><li>Thirst, dark urine, and fatigue are early signs to drink up.</li></ul><p class='wiki-tip'><i class='fas fa-lightbulb'></i> Thirst is sometimes mistaken for hunger — have a glass of water before snacking.</p>",
    ],
    [
        'cat' => 'habits', 'icon' => 'fa-utensils', 'mins' => '3 min',
        'title' => 'Building a Balanced Plate',
        'summary' => 'A no-weighing template for balanced meals.',
        'body' => "<p>You do not need to weigh every meal. A simple plate template keeps nutrition balanced almost automatically.</p><ul><li><strong>Half the plate</strong> — vegetables and fruit.</li><li><strong>A quarter</strong> — lean protein.</li><li><strong>A quarter</strong> — whole-grain or starchy carbs.</li><li>Add a small amount of healthy fat for flavor and satiety.</li></ul><p class='wiki-tip'><i class='fas fa-lightbulb'></i> Build the plate in that order — veg first, and portions take care of themselves.</p>",
    ],
    [
        'cat' => 'habits', 'icon' => 'fa-hand', 'mins' => '2 min',
        'title' => 'Portion Control Without Counting',
        'summary' => 'Use your hand to size portions anywhere.',
        'body' => "<p>Portion sizes have quietly grown over the decades. Your hand is a calibrated, always-available measuring tool.</p><ul><li><strong>Palm</strong> — one protein serving.</li><li><strong>Cupped hand</strong> — one carb serving.</li><li><strong>Fist</strong> — one vegetable serving.</li><li><strong>Thumb</strong> — one serving of fats like oil, butter, or nut butter.</li></ul><p class='wiki-tip'><i class='fas fa-lightbulb'></i> Eat slowly and pause before seconds — fullness signals take about 20 minutes.</p>",
    ],
    [
        'cat' => 'habits', 'icon' => 'fa-tag', 'mins' => '3 min',
        'title' => 'How to Read a Nutrition Label',
        'summary' => 'Decode serving sizes, macros, and ingredient lists.',
        'body' => "<p>A nutrition label tells you exactly what you are eating — once you know where to look.</p><ul><li><strong>Serving size</strong> — every number is per serving, and packs often hold several.</li><li><strong>Calories</strong> — total energy per serving.</li><li><strong>Macros</strong> — protein, carbs (including sugars), and fat.</li><li><strong>Ingredients</strong> — listed by weight; the first few matter most.</li></ul><p class='wiki-tip'><i class='fas fa-lightbulb'></i> Check the serving size first — a small snack can secretly be two or three servings.</p>",
    ],
    [
        'cat' => 'habits', 'icon' => 'fa-clock', 'mins' => '2 min',
        'title' => 'Smart Snacking & Meal Timing',
        'summary' => 'When to eat and how to snack with intention.',
        'body' => "<p>When you eat matters less than what and how much — but smart timing makes a healthy diet easier to sustain.</p><ul><li>Snacks are not bad; <strong>unplanned</strong> snacks are. Choose protein- or fiber-rich options.</li><li>Total daily intake drives weight far more than meal frequency.</li><li>Eating around workouts can support energy and recovery.</li></ul><p class='wiki-tip'><i class='fas fa-lightbulb'></i> Keep a ready snack on hand — being over-hungry leads to bigger, faster choices.</p>",
    ],
    [
        'cat' => 'habits', 'icon' => 'fa-cube', 'mins' => '3 min',
        'title' => 'Cutting Down on Added Sugar',
        'summary' => 'Spot hidden sugar and trim it without going extreme.',
        'body' => "<p>Added sugar delivers quick energy with little nutrition. Naturally occurring sugar in fruit and milk comes packaged with fiber, vitamins, and protein — that is different.</p><ul><li>Sugary drinks are the biggest hidden source for many people.</li><li>It hides under many names: syrup, dextrose, maltose, fruit-juice concentrate.</li><li>You do not need zero sugar — just less of the added kind.</li></ul><p class='wiki-tip'><i class='fas fa-lightbulb'></i> Swap one sugary drink a day for water or unsweetened tea — an easy, durable win.</p>",
    ],
];
?>

<!DOCTYPE html>
<html lang="en"
    data-theme="<?php echo isset($_SESSION['user']) ? ($_SESSION['user']['theme_preference'] ?? 'system') : 'system'; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nutrition Wiki | BitBalance</title>

    <?php
    $pageComponents = ['sidebar', 'fab'];
    $pageCss = ['css/dashboard.css', 'css/pages/dashboard-wiki.css'];
    include PROJECT_ROOT . 'views/head_css.php';
    ?>

    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
</head>

<body class="<?= htmlspecialchars($bodyClass ?? '', ENT_QUOTES) ?>">
    <?php include PROJECT_ROOT . 'views/header.php'; ?>
    <?php include PROJECT_ROOT . 'dashboard/views/sidebar.php'; ?>

    <main class="dashboard-content wiki-page">
        <div class="wiki-container">

            <section class="wiki-hero">
                <div class="wiki-hero-icon"><i class="fas fa-book-medical"></i></div>
                <div>
                    <h1>Nutrition Wiki</h1>
                    <p>Bite-sized, science-backed nutrition knowledge to fuel your goals.</p>
                </div>
            </section>

            <div class="wiki-toolbar">
                <div class="wiki-search">
                    <i class="fas fa-search"></i>
                    <input type="text" id="wikiSearch" placeholder="Search articles..."
                        aria-label="Search nutrition articles">
                </div>
                <div class="wiki-filters" id="wikiFilters">
                    <button type="button" class="wiki-pill active" data-cat="all">All</button>
                    <?php foreach ($wikiCategories as $key => $cat): ?>
                        <button type="button" class="wiki-pill" data-cat="<?= htmlspecialchars($key) ?>">
                            <i class="fas <?= htmlspecialchars($cat['icon']) ?>"></i>
                            <?= htmlspecialchars($cat['label']) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="wiki-grid" id="wikiGrid">
                <?php foreach ($wikiArticles as $a):
                    $search = strtolower($a['title'] . ' ' . $a['summary'] . ' ' . ($wikiCategories[$a['cat']]['label'] ?? ''));
                    ?>
                    <article class="wiki-article" data-cat="<?= htmlspecialchars($a['cat']) ?>"
                        data-search="<?= htmlspecialchars($search) ?>">
                        <button type="button" class="wiki-article-head" aria-expanded="false">
                            <span class="wiki-article-icon cat-<?= htmlspecialchars($a['cat']) ?>">
                                <i class="fas <?= htmlspecialchars($a['icon']) ?>"></i>
                            </span>
                            <span class="wiki-article-titles">
                                <span class="wiki-article-title"><?= htmlspecialchars($a['title']) ?></span>
                                <span class="wiki-article-summary"><?= htmlspecialchars($a['summary']) ?></span>
                            </span>
                            <span class="wiki-article-meta"><i class="far fa-clock"></i> <?= htmlspecialchars($a['mins']) ?></span>
                            <i class="fas fa-chevron-down wiki-chevron"></i>
                        </button>
                        <div class="wiki-article-body">
                            <div class="wiki-article-body-inner">
                                <div class="wiki-article-content"><?= $a['body'] ?></div>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <p class="wiki-no-results" id="wikiNoResults" hidden>No articles match your search.</p>

            <p class="wiki-disclaimer">
                <i class="fas fa-info-circle"></i>
                This guide offers general nutrition education and is not a substitute for personalized
                advice from a doctor or registered dietitian.
            </p>

        </div>
    </main>

    <?php if ($isLoggedIn): include PROJECT_ROOT . 'dashboard/views/quick-log-fab.php'; endif; ?>

    <script>
        (function () {
            const search = document.getElementById('wikiSearch');
            const grid = document.getElementById('wikiGrid');
            const articles = Array.from(grid.querySelectorAll('.wiki-article'));
            const pills = Array.from(document.querySelectorAll('.wiki-pill'));
            const noResults = document.getElementById('wikiNoResults');
            let activeCat = 'all';

            function applyFilters() {
                const q = search.value.trim().toLowerCase();
                let visible = 0;
                articles.forEach(a => {
                    const matchCat = activeCat === 'all' || a.dataset.cat === activeCat;
                    const matchText = !q || a.dataset.search.includes(q);
                    const show = matchCat && matchText;
                    a.hidden = !show;
                    if (show) visible++;
                });
                noResults.hidden = visible !== 0;
            }

            search.addEventListener('input', applyFilters);

            pills.forEach(pill => {
                pill.addEventListener('click', () => {
                    pills.forEach(p => p.classList.remove('active'));
                    pill.classList.add('active');
                    activeCat = pill.dataset.cat;
                    applyFilters();
                });
            });

            articles.forEach(a => {
                const head = a.querySelector('.wiki-article-head');
                head.addEventListener('click', () => {
                    const open = a.classList.toggle('open');
                    head.setAttribute('aria-expanded', open ? 'true' : 'false');
                });
            });
        })();
    </script>

    <?php include PROJECT_ROOT . 'views/footer.php'; ?>
</body>

</html>
