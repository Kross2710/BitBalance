<?php
/**
 * Unit tests for the Mascot OpenRouter API integration.
 *
 * Covers the pure request-building and response-parsing helpers used by
 * dashboard/handlers/mascot_chat.php when it talks to the OpenRouter
 * /chat/completions endpoint. No network, session, or DB is required —
 * these exercise the exact logic that decides whether an OpenRouter reply
 * is usable and how it is cleaned before reaching the user.
 */

require_once __DIR__ . '/../../dashboard/handlers/mascot_ai.php';

class MascotOpenRouterTest {
    public $useDatabase = false; // Pure unit test — no DB needed

    /** Helper: wrap text in a well-formed OpenRouter success body. */
    private function body($content) {
        return json_encode([
            'id' => 'gen-123',
            'choices' => [
                ['message' => ['role' => 'assistant', 'content' => $content]],
            ],
        ]);
    }

    // ── Request building ────────────────────────────────────────────────

    public function testPayloadStructure() {
        $payload = mascot_openrouter_payload('google/gemini-2.5-flash:free', 'You are the Blue Owl...');

        Assert::equals('google/gemini-2.5-flash:free', $payload['model']);
        Assert::true(is_array($payload['messages']), 'messages must be an array');
        Assert::equals(1, count($payload['messages']));
        Assert::equals('user', $payload['messages'][0]['role']);
        Assert::equals('You are the Blue Owl...', $payload['messages'][0]['content']);
    }

    public function testPayloadIsJsonEncodable() {
        // The endpoint feeds this straight into json_encode() for curl.
        $payload = mascot_openrouter_payload('x/y:free', "Quote \" and emoji 🦉 and Việt");
        $json = json_encode($payload);

        Assert::notNull($json, 'payload must serialize to JSON');
        Assert::false($json === false, 'json_encode must not fail');

        $roundTrip = json_decode($json, true);
        Assert::equals("Quote \" and emoji 🦉 and Việt", $roundTrip['messages'][0]['content']);
    }

    public function testModelFallsBackToFreeDefault() {
        // In the test process secrets.php is not loaded, so the constant is
        // absent and the helper must return the documented free default.
        if (defined('OPENROUTER_MODEL')) {
            Assert::equals(OPENROUTER_MODEL, mascot_openrouter_model());
        } else {
            Assert::equals('google/gemini-2.5-flash:free', mascot_openrouter_model());
        }
    }

    // ── Response parsing: happy paths ───────────────────────────────────

    public function testExtractsCaptionFromValidResponse() {
        $caption = mascot_openrouter_extract_caption(200, $this->body('Keep shining, friend!'));
        Assert::equals('Keep shining, friend!', $caption);
    }

    public function testStripsStraightQuotes() {
        $caption = mascot_openrouter_extract_caption(200, $this->body('"Great job today!"'));
        Assert::equals('Great job today!', $caption);
    }

    public function testStripsSmartQuotes() {
        $caption = mascot_openrouter_extract_caption(200, $this->body('“Bạn làm tốt lắm!”'));
        Assert::equals('Bạn làm tốt lắm!', $caption);
    }

    public function testTrimsSurroundingWhitespace() {
        $caption = mascot_openrouter_extract_caption(200, $this->body("  \n  Rise and shine!  \n "));
        Assert::equals('Rise and shine!', $caption);
    }

    public function testTruncatesLongCaptionToMaxLength() {
        $long = str_repeat('a', 300);
        $caption = mascot_openrouter_extract_caption(200, $this->body($long));
        Assert::equals(140, strlen($caption), 'ASCII caption should be capped at 140 chars');
    }

    public function testRespectsCustomMaxLength() {
        $caption = mascot_openrouter_extract_caption(200, $this->body('abcdefghij'), 4);
        Assert::equals('abcd', $caption);
    }

    public function testTruncationIsUtf8SafeForVietnamese() {
        // 200 multi-byte characters must cap to 140 *characters*, not bytes,
        // and must never split a character into invalid UTF-8.
        $vi = str_repeat('ố', 200);
        $caption = mascot_openrouter_extract_caption(200, $this->body($vi));

        Assert::equals(140, preg_match_all('/./us', $caption), 'should keep 140 UTF-8 chars');
        // preg '//u' returns 1 for valid UTF-8, false otherwise — mbstring-free (RMIT has no mb_*).
        Assert::equals(1, preg_match('//u', $caption), 'must remain valid UTF-8');
    }

    // ── Response parsing: rejection paths (caller falls through) ─────────

    public function testRejectsNon200StatusCodes() {
        $valid = $this->body('this should be ignored');
        Assert::null(mascot_openrouter_extract_caption(401, $valid), '401 unauthorized → null');
        Assert::null(mascot_openrouter_extract_caption(429, $valid), '429 rate limited → null');
        Assert::null(mascot_openrouter_extract_caption(500, $valid), '500 server error → null');
        Assert::null(mascot_openrouter_extract_caption(0, $valid),   'curl failure (0) → null');
    }

    public function testRejectsEmptyContent() {
        Assert::null(mascot_openrouter_extract_caption(200, $this->body('')), 'empty content → null');
    }

    public function testRejectsWhitespaceOrQuoteOnlyContent() {
        // Content that collapses to empty once trimmed/de-quoted is unusable.
        Assert::null(mascot_openrouter_extract_caption(200, $this->body('   ')), 'whitespace only → null');
        Assert::null(mascot_openrouter_extract_caption(200, $this->body('""')), 'quotes only → null');
    }

    public function testRejectsMalformedJson() {
        Assert::null(mascot_openrouter_extract_caption(200, 'not json at all'), 'garbage body → null');
        Assert::null(mascot_openrouter_extract_caption(200, ''), 'empty body → null');
        Assert::null(mascot_openrouter_extract_caption(200, 'null'), 'literal null body → null');
    }

    public function testRejectsErrorShapedPayload() {
        // A 200 with an OpenRouter error envelope (no choices) is not usable.
        $errBody = json_encode(['error' => ['message' => 'No endpoints found', 'code' => 404]]);
        Assert::null(mascot_openrouter_extract_caption(200, $errBody), 'error envelope → null');
    }

    public function testRejectsMissingMessageContentPath() {
        // choices present but the message/content path is incomplete.
        $noContent = json_encode(['choices' => [['message' => ['role' => 'assistant']]]]);
        Assert::null(mascot_openrouter_extract_caption(200, $noContent), 'missing content → null');

        $noMessage = json_encode(['choices' => [['finish_reason' => 'stop']]]);
        Assert::null(mascot_openrouter_extract_caption(200, $noMessage), 'missing message → null');

        $emptyChoices = json_encode(['choices' => []]);
        Assert::null(mascot_openrouter_extract_caption(200, $emptyChoices), 'empty choices → null');
    }
}
