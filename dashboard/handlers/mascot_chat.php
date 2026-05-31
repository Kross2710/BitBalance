<?php
/**
 * dashboard/handlers/mascot_chat.php
 * "AI Mascot Room & Health Aura" — AJAX endpoint that generates a short,
 * uplifting, body-positive speech bubble caption from the Mascot Owl.
 *
 * POST: calories, goal, protein, protein_goal, streak, vibe_state
 * Returns: { ok: bool, caption: string, cached: bool, source: string }
 */
require_once __DIR__ . '/../../include/init.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json');

function mascot_utf8_substr($text, $start, $length)
{
    $text = (string) $text;
    if ($text === '') {
        return '';
    }
    if (function_exists('iconv_substr')) {
        $slice = @iconv_substr($text, $start, $length, 'UTF-8');
        if ($slice !== false) {
            return $slice;
        }
    }
    if (preg_match_all('/./us', $text, $chars)) {
        return implode('', array_slice($chars[0], (int) $start, (int) $length));
    }
    return substr($text, (int) $start, (int) $length);
}

if (!isset($_SESSION['user'])) {
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = (int) $_SESSION['user']['user_id'];
$lang = (function_exists('current_locale') && current_locale() === 'vi') ? 'vi' : 'en';

// --- Parse input parameters ---
$calories = max(0, (int)($_POST['calories'] ?? 0));
$goal = max(0, (int)($_POST['goal'] ?? 0));
$protein = max(0, (int)($_POST['protein'] ?? 0));
$proteinGoal = max(0, (int)($_POST['protein_goal'] ?? 0));
$streak = max(0, (int)($_POST['streak'] ?? 0));
$vibeState = trim((string)($_POST['vibe_state'] ?? 'neutral'));

$allowedStates = ['healthy', 'overlimit', 'deficit', 'neutral'];
if (!in_array($vibeState, $allowedStates, true)) {
    $vibeState = 'neutral';
}

// --- Check session-based cache to save token costs & load instantly ---
$cacheKey = md5($calories . '|' . $goal . '|' . $protein . '|' . $proteinGoal . '|' . $streak . '|' . $vibeState . '|' . $lang);
if (isset($_SESSION['mascot_chat_cache']) && is_array($_SESSION['mascot_chat_cache'])) {
    if (isset($_SESSION['mascot_chat_cache'][$cacheKey])) {
        $cachedData = $_SESSION['mascot_chat_cache'][$cacheKey];
        echo json_encode(array_merge(['ok' => true, 'cached' => true], $cachedData));
        exit;
    }
}

// --- Construct Prompt (Cute Owl Mascot Roleplay) ---
$stateContextVi = '';
$stateContextEn = '';

if ($vibeState === 'healthy') {
    $stateContextVi = "Người dùng đang ăn uống rất lành mạnh, đầy đủ dinh dưỡng và gần hoặc đạt mục tiêu calo hôm nay. Bạn đang cảm thấy tràn đầy năng lượng, có hào quang xanh (Green Aura) tỏa sáng rực rỡ xung quanh. Hãy khen ngợi sự kiên trì và kỷ luật thép của họ!";
    $stateContextEn = "The user is eating very healthily, keeping balanced nutrition, and is comfortably near or met their calorie goal. You feel energized, and a bright green aura is glowing around you. Praise their dedication and discipline!";
} elseif ($vibeState === 'overlimit') {
    $stateContextVi = "Người dùng đã ăn vượt quá calo mục tiêu hôm nay. Bạn đang ở trạng thái 'no nê và buồn ngủ' (Zzz), chuẩn bị ngủ khò. [QUY TẮC]: KHÔNG chỉ trích hay gây áp lực cho họ! Hãy khuyên họ nhẹ nhàng rằng cơ thể cần được nghỉ ngơi, giấc ngủ phục hồi là quan trọng nhất, hôm nay hãy thư giãn và ngày mai chúng ta sẽ lại cùng nhau cố gắng!";
    $stateContextEn = "The user has exceeded their daily calorie target today. You are full and sleepy (Zzz), resting in bed. [RULE]: DO NOT judge or make them feel bad! Gently suggest that rest and good recovery sleep are essential, encourage them to relax tonight, and remind them that tomorrow is a fresh new start!";
} elseif ($vibeState === 'deficit') {
    $stateContextVi = "Người dùng nạp đủ calo nhưng lượng chất đạm (protein) lại bị thiếu nghiêm trọng. Bạn đang đeo băng trán thể thao và cố gắng nâng tạ mini. Hãy động viên họ nạp thêm một chút đạm chất lượng (như ức gà, trứng, sữa) để cơ bắp ta khỏe mạnh nhé!";
    $stateContextEn = "The user has logged calories but is significantly low on protein. You are wearing a sweatband and lifting tiny weights. Encourage them to add some high-quality protein (like chicken breast, eggs, or whey) so we can stay strong together!";
} else {
    $stateContextVi = "Hôm nay người dùng chưa ghi nhận món ăn nào cả. Bạn đang đứng chờ nắp hộp cơm mở ra. Hãy rủ họ ghi nhận bữa ăn đầu tiên để bắt đầu một ngày tuyệt vời cùng nhau!";
    $stateContextEn = "The user hasn't logged any meals yet today. You are waiting for them to open their lunch box. Invite them to log their first bite of the day and start an awesome tracking journey together!";
}

if ($lang === 'vi') {
    $systemPrompt = "Bạn là chú Cú Xanh - Mascot ảo dễ thương, thông thái và ấm áp của ứng dụng BitBalance. Người dùng đang xem bạn trong căn phòng linh vật trên Dashboard.
Số liệu hôm nay:
- Đã ăn: {$calories} / {$goal} kcal
- Protein: {$protein} / {$proteinGoal}g
- Chuỗi kỷ luật Streak: {$streak} ngày
Trạng thái cảm xúc của bạn lúc này: {$vibeState}
Mô tả trạng thái: {$stateContextVi}

Nhiệm vụ: Hãy đóng vai chú Cú nói một câu nhận xét ngắn (dưới 18 từ) bằng tiếng Việt siêu đáng yêu, ấm áp, hóm hỉnh.
- [QUY TẮC TUYỆT ĐỐI]: HOÀN TOÀN KHÔNG body shaming, chê bai ngoại hình, phán xét tiêu cực về cân nặng hay thói quen ăn uống của người dùng. Hãy luôn là người đồng hành đáng yêu, truyền cảm hứng tích cực.

Chỉ trả về chuỗi văn bản thô đại diện cho lời thoại của chú Cú (không markdown, không bọc nháy kép ngoài cùng):";
} else {
    $systemPrompt = "You are the Blue Owl - the cute, wise, and warm virtual Mascot of the BitBalance app. The user is viewing you in your mascot room on their dashboard.
Today's metrics:
- Intake: {$calories} / {$goal} kcal
- Protein: {$protein} / {$proteinGoal}g
- Logging Streak: {$streak} days
Your current emotional state: {$vibeState}
State details: {$stateContextEn}

Task: Roleplay as the Owl and speak one short speech caption (under 18 words) in English that is extremely warm, wise, and adorable.
- [ABSOLUTE RULE]: NEVER comment on weight, fatness, body shape, or body-shame the user. Never judge or shame their diet negatively. Always be a warm, encouraging, positive companion.

Return ONLY the raw text representing the owl's speech (no markdown, no surrounding quotes):";
}

// --- API Execution with fallback layer ---
$caption = '';
$source = '';
$success = false;

// 1. Try OpenRouter if configured
if (defined('OPENROUTER_API_KEY') && OPENROUTER_API_KEY !== '') {
    try {
        $model = defined('OPENROUTER_MODEL') ? OPENROUTER_MODEL : 'google/gemini-2.5-flash:free';
        $apiUrl = 'https://openrouter.ai/api/v1/chat/completions';
        
        $data = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $systemPrompt]
            ]
        ];
        
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . OPENROUTER_API_KEY,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $resData = json_decode($response, true);
            $aiText = $resData['choices'][0]['message']['content'] ?? '';
            $aiText = trim(str_replace(['"', '“', '”'], '', $aiText));
            if ($aiText !== '') {
                $caption = mascot_utf8_substr($aiText, 0, 140);
                $source = 'openrouter';
                $success = true;
            }
        }
    } catch (Exception $e) {
        // Fall through to local Gemini fallback
    }
}

// 2. Seamless local Gemini API Fallback if OpenRouter failed or was not configured
if (!$success) {
    try {
        if (defined('GEMINI_API_KEY') && GEMINI_API_KEY !== '') {
            $geminiModel = defined('AI_COACH_MODEL') ? AI_COACH_MODEL : 'gemini-3.1-flash-lite';
            $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$geminiModel}:generateContent?key=" . GEMINI_API_KEY;
            
            $body = ['contents' => [['parts' => [['text' => $systemPrompt]]]]];
            
            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 12);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                $resJson = json_decode($response, true);
                $rawText = $resJson['candidates'][0]['content']['parts'][0]['text'] ?? '';
                $cleanText = trim(str_replace(['"', '“', '”', '```json', '```'], '', $rawText));
                if ($cleanText !== '') {
                    $caption = mascot_utf8_substr($cleanText, 0, 140);
                    $source = 'gemini_fallback';
                    $success = true;
                }
            }
        }
    } catch (Exception $e) {
        // Fall through to hardcoded static fallbacks
    }
}

// 3. Static fallback backup if all AI services are offline
if (!$success || $caption === '') {
    $source = 'static_fallback';
    if ($lang === 'vi') {
        if ($vibeState === 'healthy') {
            $caption = "Bạn đang làm rất tốt! Tiếp tục duy trì phong độ ăn uống lành mạnh này nhé! 🌟";
        } elseif ($vibeState === 'overlimit') {
            $caption = "Cơ thể bạn đã no nê rồi. Đừng lo lắng về calo nữa, hãy ngủ một giấc thật ngon nhé! 🌙";
        } elseif ($vibeState === 'deficit') {
            $caption = "Bạn cần thêm một chút protein để cơ bắp ta khỏe mạnh hơn. Thêm trứng hoặc ức gà nhé! 💪";
        } else {
            $caption = "Chào ngày mới! Bắt đầu ngày tuyệt vời bằng việc ghi nhận bữa ăn đầu tiên nhé! 🍳";
        }
    } else {
        if ($vibeState === 'healthy') {
            $caption = "You are doing amazing! Keep up this great, healthy eating momentum! 🌟";
        } elseif ($vibeState === 'overlimit') {
            $caption = "Your body is well-fueled. Don't stress, relax and get some beautiful sleep! 🌙";
        } elseif ($vibeState === 'deficit') {
            $caption = "We need some quality protein to stay strong. Grab some eggs or chicken! 💪";
        } else {
            $caption = "Rise and shine! Let's start this beautiful day by logging your first bite! 🍳";
        }
    }
}

// --- Save to Session Cache ---
$resultData = [
    'caption' => $caption,
    'source' => $source
];

if (!isset($_SESSION['mascot_chat_cache']) || !is_array($_SESSION['mascot_chat_cache'])) {
    $_SESSION['mascot_chat_cache'] = [];
}
$_SESSION['mascot_chat_cache'][$cacheKey] = $resultData;

// --- Output JSON ---
echo json_encode(array_merge(['ok' => true, 'cached' => false], $resultData));
exit;
