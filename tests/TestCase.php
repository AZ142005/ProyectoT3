<?php
namespace Tests;

class TestCase {
    protected int $passed = 0;
    protected int $failed = 0;
    protected int $errors = 0;
    protected int $skipped = 0;
    protected array $failures = [];

    public function assertTrue(bool $condition, string $message = ''): void {
        if ($condition) {
            $this->passed++;
        } else {
            $this->failed++;
            $this->failures[] = "ASSERT TRUE failed: {$message}";
        }
    }

    public function assertFalse(bool $condition, string $message = ''): void {
        if (!$condition) {
            $this->passed++;
        } else {
            $this->failed++;
            $this->failures[] = "ASSERT FALSE failed: {$message}";
        }
    }

    public function assertEquals($expected, $actual, string $message = ''): void {
        if ($expected === $actual) {
            $this->passed++;
        } else {
            $this->failed++;
            $expectedStr = var_export($expected, true);
            $actualStr = var_export($actual, true);
            $this->failures[] = "ASSERT EQUALS failed: {$message}\n  Expected: {$expectedStr}\n  Actual:   {$actualStr}";
        }
    }

    public function assertNotEquals($expected, $actual, string $message = ''): void {
        if ($expected !== $actual) {
            $this->passed++;
        } else {
            $this->failed++;
            $this->failures[] = "ASSERT NOT_EQUALS failed: {$message}\n  Value should not be: " . var_export($expected, true);
        }
    }

    public function assertNull($value, string $message = ''): void {
        if ($value === null) {
            $this->passed++;
        } else {
            $this->failed++;
            $this->failures[] = "ASSERT NULL failed: {$message}\n  Value: " . var_export($value, true);
        }
    }

    public function assertNotNull($value, string $message = ''): void {
        if ($value !== null) {
            $this->passed++;
        } else {
            $this->failed++;
            $this->failures[] = "ASSERT NOT_NULL failed: {$message}";
        }
    }

    public function assertGreaterThan($expected, $actual, string $message = ''): void {
        if ($actual > $expected) {
            $this->passed++;
        } else {
            $this->failed++;
            $this->failures[] = "ASSERT GREATER_THAN failed: {$message}\n  Expected > {$expected}, got {$actual}";
        }
    }

    public function assertStringContains(string $needle, string $haystack, string $message = ''): void {
        if (str_contains($haystack, $needle)) {
            $this->passed++;
        } else {
            $this->failed++;
            $this->failures[] = "ASSERT STRING_CONTAINS failed: {$message}\n  '{$needle}' not found in string";
        }
    }

    public function assertContains($needle, array $haystack, string $message = ''): void {
        if (in_array($needle, $haystack)) {
            $this->passed++;
        } else {
            $this->failed++;
            $this->failures[] = "ASSERT CONTAINS failed: {$message}\n  " . var_export($needle, true) . " not found in array";
        }
    }

    public function assertMatchesRegex(string $pattern, string $subject, string $message = ''): void {
        if (preg_match($pattern, $subject)) {
            $this->passed++;
        } else {
            $this->failed++;
            $this->failures[] = "ASSERT MATCHES_REGEX failed: {$message}\n  Pattern '{$pattern}' does not match";
        }
    }

    public function expectException(string $exceptionClass, callable $callable, string $message = ''): void {
        try {
            $callable();
            $this->failed++;
            $this->failures[] = "EXPECT EXCEPTION failed: {$message}\n  Expected {$exceptionClass} but no exception was thrown";
        } catch (\Throwable $e) {
            if ($e instanceof $exceptionClass) {
                $this->passed++;
            } else {
                $this->failed++;
                $this->failures[] = "EXPECT EXCEPTION failed: {$message}\n  Expected {$exceptionClass}, got " . get_class($e);
            }
        }
    }

    public function expectExceptionMessage(string $expectedMessage, callable $callable, string $message = ''): void {
        try {
            $callable();
            $this->failed++;
            $this->failures[] = "EXPECT EXCEPTION MESSAGE failed: {$message}\n  Expected exception with message '{$expectedMessage}' but no exception was thrown";
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), $expectedMessage)) {
                $this->passed++;
            } else {
                $this->failed++;
                $this->failures[] = "EXPECT EXCEPTION MESSAGE failed: {$message}\n  Expected message containing '{$expectedMessage}', got '{$e->getMessage()}'";
            }
        }
    }

    public function assertIsArray($value, string $message = ''): void {
        if (is_array($value)) {
            $this->passed++;
        } else {
            $this->failed++;
            $this->failures[] = "ASSERT IS_ARRAY failed: {$message}\n  Type: " . gettype($value);
        }
    }

    public function assertIsBool($value, string $message = ''): void {
        if (is_bool($value)) {
            $this->passed++;
        } else {
            $this->failed++;
            $this->failures[] = "ASSERT IS_BOOL failed: {$message}\n  Type: " . gettype($value);
        }
    }

    public function assertFileExists(string $path, string $message = ''): void {
        if (file_exists($path)) {
            $this->passed++;
        } else {
            $this->failed++;
            $this->failures[] = "ASSERT FILE_EXISTS failed: {$message}\n  File not found: {$path}";
        }
    }

    public function assertDirectoryExists(string $path, string $message = ''): void {
        if (is_dir($path)) {
            $this->passed++;
        } else {
            $this->failed++;
            $this->failures[] = "ASSERT DIRECTORY_EXISTS failed: {$message}\n  Directory not found: {$path}";
        }
    }

    public function addError(string $message): void {
        $this->errors++;
        $this->failures[] = $message;
    }

    public function skip(string $reason = ''): void {
        $this->skipped++;
        if ($reason) {
            $this->failures[] = "SKIPPED: {$reason}";
        }
    }

    public function results(): array {
        return [
            'passed'  => $this->passed,
            'failed'  => $this->failed,
            'errors'  => $this->errors,
            'skipped' => $this->skipped,
            'failures' => $this->failures,
        ];
    }
}
