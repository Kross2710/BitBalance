<?php
// dashboard/handlers/ai_chat.php
require_once __DIR__ . '/../../include/init.php';
// require_once __DIR__ . '/../../include/db_config.php'; 
// Nếu db_config.php đã được include trong init.php thì bỏ dòng trên, nếu chưa thì giữ lại.
// Đảm bảo GEMINI_API_KEY đã được define đúng.

header('Content-Type: application/json');

// Check Login
if (!isset($_SESSION['user']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Unauthorized access']);
    exit();
}

$textInput = $_POST['message'] ?? '';
$imageFile = $_FILES['image'] ?? null;

if (empty($textInput) && empty($imageFile)) {
    echo json_encode(['ok' => false, 'error' => 'Empty input']);
    exit();
}

// Prompt "ép" JSON
$systemPrompt = "You are a professional Nutritionist AI. 
Analyze the food in the image or the text description provided.
Estimate the nutritional values.
If the input is NOT food, return fields with null.
Strictly return ONLY a raw JSON object (no markdown formatting, no ```json wrappers) with this structure:
{
    \"food_name\": \"Name of the food\",
    \"calories\": 0,
    \"protein\": 0,
    \"carbs\": 0,
    \"fat\": 0,
    \"unit\": \"1 serving/bowl/plate\",
    \"short_advice\": \"A short 1-sentence health tip about this food.\"
}";

// Xây dựng Payload
$parts = [];
if (!empty($textInput)) {
    $parts[] = ['text' => $systemPrompt . "\n\nUser Input: " . $textInput];
} else {
    $parts[] = ['text' => $systemPrompt];
}

// Xử lý ảnh
$imagePath = null;
if ($imageFile && $imageFile['error'] === UPLOAD_ERR_OK) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $mimeType = $imageFile['type'];
    if (in_array($mimeType, $allowed_types, true)) {
        $uploadDir = __DIR__ . '/../../uploads/intake/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $ext = pathinfo($imageFile['name'], PATHINFO_EXTENSION);
        if (empty($ext)) {
            $ext = $mimeType === 'image/png' ? 'png' : ($mimeType === 'image/gif' ? 'gif' : 'jpg');
        }
        $filename = 'meal_' . $_SESSION['user']['user_id'] . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $fsPath = $uploadDir . $filename;
        if (move_uploaded_file($imageFile['tmp_name'], $fsPath)) {
            $imagePath = 'uploads/intake/' . $filename;
            $imageData = base64_encode(file_get_contents($fsPath));
            $parts[] = [
                'inline_data' => [
                    'mime_type' => $mimeType,
                    'data' => $imageData
                ]
            ];
        }
    }
}

// Gửi Request
$apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent?key=" . GEMINI_API_KEY;

$body = [
    'contents' => [
        ['parts' => $parts]
    ]
];

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));

// FIX LỖI SSL CHO XAMPP/LOCALHOST
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);

// Bắt lỗi kết nối cURL
if (curl_errno($ch)) {
    echo json_encode(['ok' => false, 'error' => 'Connection Error: ' . curl_error($ch)]);
    exit();
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Xử lý kết quả từ Google
if ($httpCode === 200) {
    $data = json_decode($response, true);
    $rawText = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    
    // Clean JSON text
    $cleanText = str_replace(['```json', '```'], '', $rawText);
    $cleanText = trim($cleanText);
    
    $nutritionData = json_decode($cleanText, true);

    if ($nutritionData) {
        echo json_encode([
            'ok' => true, 
            'data' => $nutritionData,
            'image_path' => $imagePath
        ]);
    } else {
        // Trả về raw text để debug nếu AI không trả JSON đúng
        echo json_encode(['ok' => false, 'error' => 'AI format error. Raw: ' . $rawText]);
    }
} else {
    // In ra lỗi từ Google (ví dụ sai API Key)
    $errData = json_decode($response, true);
    $errMsg = $errData['error']['message'] ?? 'Unknown API Error';
    echo json_encode(['ok' => false, 'error' => "Google Error ($httpCode): $errMsg"]);
}
?>