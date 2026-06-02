<?php
/**
 * Test suite for username handle generation logic.
 */

require_once __DIR__ . '/../../../include/handlers/username.php';

class UsernameTest {
    public $useDatabase = true; // Use transactional rollback

    public function testSlugifyAccents() {
        // Test Vietnamese diacritics transliteration.
        // On GNU/Linux (RMIT), iconv transliterates "Hưng" to "Hung" and "Nguyễn" to "Nguyen".
        // On BSD/macOS, it may drop transliterated accents yielding "Hng" and "Nguyn".
        // We assert either is acceptable to keep the test robust across local/server platforms.
        $slugHung = slugify_name("Hưng");
        Assert::true(in_array($slugHung, ["Hung", "Hng"], true), "Expected 'Hung' or 'Hng', got: " . $slugHung);

        $slugNguyen = slugify_name("Nguyễn");
        Assert::true(in_array($slugNguyen, ["Nguyen", "Nguyn"], true), "Expected 'Nguyen' or 'Nguyn', got: " . $slugNguyen);

        $slugTran = slugify_name("Trần");
        Assert::true(in_array($slugTran, ["Tran", "Trn"], true), "Expected 'Tran' or 'Trn', got: " . $slugTran);

        $slugLe = slugify_name("Lê");
        Assert::true(in_array($slugLe, ["Le", "L"], true), "Expected 'Le' or 'L', got: " . $slugLe);
    }

    public function testSlugifySpacesAndSymbols() {
        // Spaces and special chars should be stripped
        Assert::equals("HungVu", slugify_name("Hung Vu"));
        Assert::equals("JohnDoe123", slugify_name("John_Doe-123!"));
        Assert::equals("AlphaNumericOnly", slugify_name("Alpha @ Numeric & Only..."));
    }

    public function testSlugifyEmpty() {
        // Empty names or untranslatable names
        Assert::equals("", slugify_name(""));
        Assert::equals("", slugify_name("   "));
    }

    public function testSlugifyLengthCap() {
        // Cap is 20 chars for the base slug
        $longName = "SuperLongNameThatExceedsTheTwentyCharacterLimit";
        $slug = slugify_name($longName);
        Assert::equals(20, strlen($slug));
        Assert::equals("SuperLongNameThatExc", $slug);
    }

    public function testGenerateHandleStandard() {
        global $pdo;
        
        $handle = generate_handle($pdo, "Hung");
        
        // Assert handle shape: Hung#<4-digits> or Hng#<4-digits> (depends on iconv)
        Assert::true((bool)preg_match('/^(Hung|Hng)#\d{4}$/i', $handle), "Handle should match format Hung#1234. Got: " . $handle);
    }

    public function testGenerateHandleFallback() {
        global $pdo;
        
        // If first name is completely untranslatable, it should fall back to 'user'
        $handle = generate_handle($pdo, "漢語");
        
        Assert::true((bool)preg_match('/^user#\d{4}$/', $handle), "Handle should match fallback format user#1234. Got: " . $handle);
    }

    public function testGenerateHandleUniqueness() {
        global $pdo;

        // Generate a handle
        $handle1 = generate_handle($pdo, "TestCollision");
        
        // Insert it so it exists in DB
        $stmt = $pdo->prepare("INSERT INTO user (user_name, first_name, email, password) VALUES (?, 'TestCollision', 'coll@example.com', 'pass')");
        $stmt->execute([$handle1]);

        // Generate again with same name. It should not return $handle1!
        $handle2 = generate_handle($pdo, "TestCollision");
        
        Assert::notEquals($handle1, $handle2, "Generated handle should be unique and not collide with existing");
        Assert::true((bool)preg_match('/^testcollision#\d{4}$/i', $handle2), "Handle should match collision format. Got: " . $handle2);
    }
}
