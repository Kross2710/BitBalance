<?php
/**
 * Command-line runner for BitBalance unit tests.
 * Run using: php tests/run.php
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/framework/TestRunner.php';

// Color helper
function color($text, $colorCode) {
    // If running in terminal, output ANSI colors
    if (DIRECTORY_SEPARATOR === '/' || getenv('ANSICON') !== false || strpos(getenv('TERM'), 'color') !== false) {
        return "\033[" . $colorCode . "m" . $text . "\033[0m";
    }
    return $text;
}

$targetSuite = isset($argv[1]) ? $argv[1] : null;
$targetMethod = isset($argv[2]) ? $argv[2] : null;

echo "\n" . color("=== BitBalance Test Runner ===", "1;35") . "\n";
if ($targetSuite) {
    echo "Target Suite: $targetSuite" . ($targetMethod ? " -> Method: $targetMethod" : "") . "\n";
}
echo "Bootstrapped database connection.\n\n";

$runner = new TestRunner();
$results = $runner->run($targetSuite, $targetMethod);

// Print results
foreach ($results['suites'] as $suiteName => $suite) {
    echo color("● $suiteName", "1;34") . " (" . $suite['duration'] . "ms)\n";
    
    foreach ($suite['cases'] as $case) {
        if ($case['status'] === 'passed') {
            echo "  " . color("✔", "32") . " " . $case['method'] . " (" . $case['duration'] . "ms)\n";
        } else {
            echo "  " . color("✘", "31") . " " . color($case['method'], "31") . " (" . $case['duration'] . "ms)\n";
            echo color("    Error: " . $case['error']['message'], "33") . "\n";
            echo "    File: " . $case['error']['file'] . ":" . $case['error']['line'] . "\n";
            
            if ($case['error']['expected'] !== null || $case['error']['actual'] !== null) {
                echo "    Expected: " . var_export($case['error']['expected'], true) . "\n";
                echo "    Actual:   " . var_export($case['error']['actual'], true) . "\n";
            }
            echo "\n";
        }
    }
    echo "\n";
}

echo color("==============================", "1;35") . "\n";
$stats = $results['stats'];
echo "Tests run: " . $stats['total'] . "\n";

if ($stats['failed'] > 0) {
    echo color("PASSED: " . $stats['passed'], "32") . "\n";
    echo color("FAILED: " . $stats['failed'], "31;1") . "\n";
    echo color("Result: FAILURE", "31;1") . " (Total time: " . $stats['duration'] . "ms)\n\n";
    exit(1);
} else {
    echo color("ALL PASSED (" . $stats['passed'] . " tests)", "32;1") . "\n";
    echo "Result: SUCCESS (Total time: " . $stats['duration'] . "ms)\n\n";
    exit(0);
}
