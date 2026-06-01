<?php
/**
 * dashboard/handlers/mascot_ai.php
 *
 * Pure, side-effect-free helpers for the AI Mascot speech-bubble feature.
 * Extracted from mascot_chat.php so the OpenRouter request building and
 * response parsing can be unit-tested without HTTP, sessions, or a DB.
 *
 * Tested by tests/suites/MascotOpenRouterTest.php
 */

if (!function_exists('mascot_utf8_substr')) {
    /**
     * UTF-8 aware substring with graceful fallbacks for hosts that lack
     * iconv (e.g. the RMIT shared PHP build).
     */
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
}

if (!function_exists('mascot_openrouter_model')) {
    /**
     * Resolve the OpenRouter model id, falling back to a free default
     * when OPENROUTER_MODEL is not configured in secrets.php.
     */
    function mascot_openrouter_model()
    {
        return defined('OPENROUTER_MODEL') ? OPENROUTER_MODEL : 'google/gemini-2.5-flash:free';
    }
}

if (!function_exists('mascot_openrouter_payload')) {
    /**
     * Build the JSON body for an OpenRouter /chat/completions request.
     * The mascot prompt is sent as a single user message.
     */
    function mascot_openrouter_payload($model, $prompt)
    {
        return [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ];
    }
}

if (!function_exists('mascot_openrouter_extract_caption')) {
    /**
     * Turn a raw OpenRouter HTTP response into a clean speech-bubble caption.
     *
     * Mirrors the success criteria used by the live endpoint: the call must
     * have returned HTTP 200 and a non-empty assistant message. Surrounding
     * quotes (straight and smart) are stripped, the text is trimmed and
     * capped at $maxLen characters. Returns null when the response is not
     * usable (non-200, malformed JSON, missing content, or empty after
     * cleaning) so the caller can fall through to its next provider.
     */
    function mascot_openrouter_extract_caption($httpCode, $responseBody, $maxLen = 140)
    {
        if ((int) $httpCode !== 200) {
            return null;
        }

        $resData = json_decode((string) $responseBody, true);
        $aiText = isset($resData['choices'][0]['message']['content'])
            ? $resData['choices'][0]['message']['content']
            : '';
        $aiText = trim(str_replace(['"', '“', '”'], '', (string) $aiText));

        if ($aiText === '') {
            return null;
        }

        return mascot_utf8_substr($aiText, 0, $maxLen);
    }
}
