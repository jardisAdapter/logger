<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Tests\Unit\Enricher;

use JardisAdapter\Logger\Enricher\LogUuid;
use PHPUnit\Framework\TestCase;

class LogUuidTest extends TestCase
{
    public function testInvocationReturnsUuid(): void
    {
        $result = (new LogUuid())();

        $this->assertIsString($result);

        $this->assertMatchesRegularExpression(
            '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i',
            $result
        );
    }

    public function testUniqueUuidGeneration(): void
    {
        $logUuid = new LogUuid();

        $uuid1 = $logUuid();
        $uuid2 = $logUuid();

        $this->assertNotSame($uuid1, $uuid2);
    }
}
