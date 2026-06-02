<?php
/**
 * Lightweight assertion framework for BitBalance.
 */

class AssertionFailedException extends Exception {
    private $expected;
    private $actual;

    public function __construct($message, $expected = null, $actual = null) {
        parent::__construct($message);
        $this->expected = $expected;
        $this->actual = $actual;
    }

    public function getExpected() { return $this->expected; }
    public function getActual() { return $this->actual; }
}

class Assert {
    public static function equals($expected, $actual, $message = '') {
        if ($expected !== $actual) {
            $msg = $message ?: "Expected: " . self::export($expected) . ", Got: " . self::export($actual);
            throw new AssertionFailedException($msg, $expected, $actual);
        }
    }

    public static function notEquals($expected, $actual, $message = '') {
        if ($expected === $actual) {
            $msg = $message ?: "Expected value to NOT equal: " . self::export($expected);
            throw new AssertionFailedException($msg, null, null);
        }
    }

    public static function true($value, $message = '') {
        if ($value !== true) {
            $msg = $message ?: "Expected TRUE, got: " . self::export($value);
            throw new AssertionFailedException($msg, true, $value);
        }
    }

    public static function false($value, $message = '') {
        if ($value !== false) {
            $msg = $message ?: "Expected FALSE, got: " . self::export($value);
            throw new AssertionFailedException($msg, false, $value);
        }
    }

    public static function null($value, $message = '') {
        if ($value !== null) {
            $msg = $message ?: "Expected NULL, got: " . self::export($value);
            throw new AssertionFailedException($msg, null, $value);
        }
    }

    public static function notNull($value, $message = '') {
        if ($value === null) {
            $msg = $message ?: "Expected NOT NULL, got NULL";
            throw new AssertionFailedException($msg, "NOT NULL", null);
        }
    }

    public static function throws(callable $callback, $exceptionClass = 'Throwable', $message = '') {
        try {
            $callback();
        } catch (Throwable $e) {
            if ($e instanceof $exceptionClass) {
                return; // Passed!
            }
            $msg = $message ?: "Expected exception of type '$exceptionClass', but caught '" . get_class($e) . "' with message: " . $e->getMessage();
            throw new AssertionFailedException($msg, $exceptionClass, get_class($e));
        }
        $msg = $message ?: "Expected exception of type '$exceptionClass' was not thrown.";
        throw new AssertionFailedException($msg, $exceptionClass, "no exception");
    }

    private static function export($val) {
        if (is_null($val)) return 'NULL';
        if (is_bool($val)) return $val ? 'TRUE' : 'FALSE';
        if (is_string($val)) return '"' . addslashes($val) . '"';
        if (is_array($val)) return 'Array(' . count($val) . ') ' . json_encode($val);
        if (is_object($val)) return 'Object(' . get_class($val) . ')';
        return (string) $val;
    }
}
