<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Tests\Helpers;

class TempFileHelper
{
    public static function create(string $prefix = 'test_'): string
    {
        return sys_get_temp_dir() . '/' . $prefix . uniqid() . '.log';
    }

    public static function cleanup(string $path): void
    {
        if (file_exists($path)) {
            @unlink($path);
        }
    }

}
