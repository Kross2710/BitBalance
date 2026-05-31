<?php
/**
 * Test runner engine for BitBalance.
 */

require_once __DIR__ . '/Assert.php';

class TestRunner {
    private $suiteDir;

    public function __construct($suiteDir = null) {
        $this->suiteDir = $suiteDir ?: dirname(__DIR__) . '/suites';
    }

    public function run($targetSuite = null, $targetMethod = null) {
        $results = [
            'stats' => [
                'total' => 0,
                'passed' => 0,
                'failed' => 0,
                'duration' => 0.0,
            ],
            'suites' => []
        ];

        $startTime = microtime(true);

        if (!is_dir($this->suiteDir)) {
            return $results;
        }

        $files = scandir($this->suiteDir);
        foreach ($files as $file) {
            if (substr($file, -8) !== 'Test.php') {
                continue;
            }

            $suiteName = substr($file, 0, -4);
            if ($targetSuite && $suiteName !== $targetSuite) {
                continue;
            }

            require_once $this->suiteDir . '/' . $file;

            if (!class_exists($suiteName)) {
                continue;
            }

            $suiteResults = $this->runSuite($suiteName, $targetMethod);
            $results['suites'][$suiteName] = $suiteResults;

            $results['stats']['total'] += $suiteResults['total'];
            $results['stats']['passed'] += $suiteResults['passed'];
            $results['stats']['failed'] += $suiteResults['failed'];
        }

        $results['stats']['duration'] = round((microtime(true) - $startTime) * 1000, 2); // In milliseconds

        return $results;
    }

    private function runSuite($className, $targetMethod = null) {
        $suiteStartTime = microtime(true);
        $cases = [];
        $passed = 0;
        $failed = 0;

        $reflection = new ReflectionClass($className);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        global $pdo;

        foreach ($methods as $method) {
            $methodName = $method->name;
            if (substr($methodName, 0, 4) !== 'test') {
                continue;
            }

            if ($targetMethod && $methodName !== $targetMethod) {
                continue;
            }

            $caseStartTime = microtime(true);
            $instance = new $className();
            $useDatabase = isset($instance->useDatabase) && $instance->useDatabase === true;

            $error = null;
            $status = 'passed';

            try {
                // Database transaction isolation
                if ($useDatabase && isset($pdo)) {
                    if ($pdo instanceof TestPDO) {
                        $pdo->realBeginTransaction();
                    } else {
                        $pdo->beginTransaction();
                    }
                }

                // Call setUp if exists
                if (method_exists($instance, 'setUp')) {
                    $instance->setUp();
                }

                // Run actual test
                $instance->$methodName();

                // Call tearDown if exists
                if (method_exists($instance, 'tearDown')) {
                    $instance->tearDown();
                }

                if ($useDatabase && isset($pdo)) {
                    if ($pdo instanceof TestPDO) {
                        $pdo->realRollback();
                    } elseif ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                }

                $passed++;
            } catch (Throwable $e) {
                $status = 'failed';
                $failed++;

                // Ensure transaction is rolled back on exception
                if ($useDatabase && isset($pdo)) {
                    try {
                        if ($pdo instanceof TestPDO) {
                            $pdo->realRollback();
                        } elseif ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                    } catch (Throwable $ignore) {}
                }

                $expected = null;
                $actual = null;
                if ($e instanceof AssertionFailedException) {
                    $expected = $e->getExpected();
                    $actual = $e->getActual();
                }

                $error = [
                    'class' => get_class($e),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'expected' => $expected,
                    'actual' => $actual,
                ];
            }

            $caseDuration = round((microtime(true) - $caseStartTime) * 1000, 2);
            $cases[] = [
                'method' => $methodName,
                'status' => $status,
                'duration' => $caseDuration,
                'error' => $error
            ];
        }

        $suiteDuration = round((microtime(true) - $suiteStartTime) * 1000, 2);

        return [
            'total' => $passed + $failed,
            'passed' => $passed,
            'failed' => $failed,
            'duration' => $suiteDuration,
            'cases' => $cases
        ];
    }
}
