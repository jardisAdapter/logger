<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Tests\Helpers;

class TempFileHelper
{
    public static function create(string $prefix = 'test_'): string
    {
        return sys_get_temp_dir() . '/' . $prefix . uniqid() . '.log';
    }

    public static function createMultiple(int $count, string $prefix = 'test_'): array
    {
        $files = [];
        for ($i = 0; $i < $count; $i++) {
            $files[] = self::create($prefix . $i . '_');
        }
        return $files;
    }

    public static function cleanup(string $path): void
    {
        if (file_exists($path)) {
            @unlink($path);
        }
    }

    public static function cleanupMultiple(array $paths): void
    {
        foreach ($paths as $path) {
            self::cleanup($path);
        }
    }

    public static function countLines(string $filePath, ?string $pattern = null): int
    {
        if (!file_exists($filePath)) {
            return 0;
        }
        $content = file_get_contents($filePath);
        if ($content === false) {
            return 0;
        }
        return $pattern === null
            ? substr_count($content, "\n")
            : substr_count($content, $pattern);
    }

    public static function assertContainsPattern(
        string $filePath,
        string $pattern,
        int $expectedCount,
        callable $assertEquals
    ): void {
        $actualCount = self::countLines($filePath, $pattern);
        $assertEquals(
            $expectedCount,
            $actualCount,
            "Expected {$expectedCount} occurrences of '{$pattern}' but found {$actualCount}"
        );
    }
}
