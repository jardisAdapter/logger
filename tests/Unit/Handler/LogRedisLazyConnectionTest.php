<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Tests\Unit\Handler;

use Closure;
use JardisAdapter\Logger\Handler\LogRedis;
use JardisAdapter\Logger\Handler\LogRedisMq;
use JardisAdapter\Logger\LoggerBuilder;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Redis;
use RedisException;
use RuntimeException;

/**
 * Lazy `Closure(): Redis` provider for LogRedis, LogRedisMq and LoggerBuilder::addRedis/addRedisMq.
 */
class LogRedisLazyConnectionTest extends TestCase
{
    /**
     * @return array{0: Closure(): Redis, 1: object{calls: int}}
     */
    private function countingProvider(Redis $redis): array
    {
        $counter = new class {
            public int $calls = 0;
        };

        return [
            function () use ($redis, $counter): Redis {
                $counter->calls++;

                return $redis;
            },
            $counter,
        ];
    }

    private function redisMockExpectingSetex(int $times): Redis
    {
        $redis = $this->createMock(Redis::class);
        $redis->expects($this->exactly($times))->method('setex')->willReturn(true);

        return $redis;
    }

    private function redisMockExpectingPublish(int $times): Redis
    {
        $redis = $this->createMock(Redis::class);
        $redis->expects($this->exactly($times))->method('publish')->willReturn(1);

        return $redis;
    }

    // ---- ready handle stays valid (both handlers) --------------------------------------------

    public function testLogRedisWithReadyHandleWritesWithoutProvider(): void
    {
        $handler = new LogRedis(LogLevel::INFO, $this->redisMockExpectingSetex(1));

        $this->assertNotNull($handler(LogLevel::INFO, 'ready handle'));
    }

    public function testLogRedisMqWithReadyHandlePublishesWithoutProvider(): void
    {
        $handler = new LogRedisMq($this->redisMockExpectingPublish(1), 'logs');

        $this->assertNotNull($handler(LogLevel::INFO, 'ready handle'));
    }

    // ---- provider: construction does not call, first access calls exactly once ----------------

    public function testLogRedisProviderIsNotCalledOnConstructionAndOnceOnFirstWrite(): void
    {
        [$provider, $counter] = $this->countingProvider($this->redisMockExpectingSetex(2));

        $handler = new LogRedis(LogLevel::INFO, $provider);
        $this->assertSame(0, $counter->calls);

        $this->assertNotNull($handler(LogLevel::INFO, 'first'));
        $this->assertSame(1, $counter->calls);

        $this->assertNotNull($handler(LogLevel::INFO, 'second'));
        $this->assertSame(1, $counter->calls);
    }

    public function testLogRedisMqProviderIsNotCalledOnConstructionAndOnceOnFirstPublish(): void
    {
        [$provider, $counter] = $this->countingProvider($this->redisMockExpectingPublish(2));

        $handler = new LogRedisMq($provider, 'logs');
        $this->assertSame(0, $counter->calls);

        $this->assertNotNull($handler(LogLevel::INFO, 'first'));
        $this->assertSame(1, $counter->calls);

        $this->assertNotNull($handler(LogLevel::INFO, 'second'));
        $this->assertSame(1, $counter->calls);
    }

    public function testLogRedisBelowLevelDoesNotCallProvider(): void
    {
        [$provider, $counter] = $this->countingProvider($this->redisMockExpectingSetex(0));

        $handler = new LogRedis(LogLevel::ERROR, $provider);
        $this->assertNull($handler(LogLevel::INFO, 'ignored'));

        $this->assertSame(0, $counter->calls);
    }

    public function testGetRedisResolvesProviderOnceAndReturnsSameHandle(): void
    {
        $redis = $this->createMock(Redis::class);
        [$provider, $counter] = $this->countingProvider($redis);

        $logRedis = new LogRedis(LogLevel::INFO, $provider);
        $this->assertSame(0, $counter->calls);
        $this->assertSame($redis, $logRedis->getRedis());
        $this->assertSame($redis, $logRedis->getRedis());
        $this->assertSame(1, $counter->calls);

        [$provider2, $counter2] = $this->countingProvider($redis);
        $logMq = new LogRedisMq($provider2, 'logs');
        $this->assertSame($redis, $logMq->getRedis());
        $this->assertSame($redis, $logMq->getRedis());
        $this->assertSame(1, $counter2->calls);
    }

    // ---- LoggerBuilder: build never calls the provider, first log calls it once --------------

    public function testBuilderAddRedisAndAddRedisMqWithProviderOpenNothingAtBuild(): void
    {
        [$providerStore, $counterStore] = $this->countingProvider($this->redisMockExpectingSetex(2));
        [$providerMq, $counterMq] = $this->countingProvider($this->redisMockExpectingPublish(2));

        $logger = (new LoggerBuilder('lazy-test'))
            ->addRedis(LogLevel::INFO, $providerStore, 'store')
            ->addRedisMq($providerMq, 'logs', 'mq')
            ->getLogger();

        $this->assertSame(0, $counterStore->calls, 'addRedis must not call the provider at build');
        $this->assertSame(0, $counterMq->calls, 'addRedisMq must not call the provider at build');

        $logger->info('first entry');
        $this->assertSame(1, $counterStore->calls);
        $this->assertSame(1, $counterMq->calls);

        $logger->info('second entry');
        $this->assertSame(1, $counterStore->calls);
        $this->assertSame(1, $counterMq->calls);
    }

    public function testBuilderAddRedisAndAddRedisMqStillAcceptReadyHandles(): void
    {
        $logger = (new LoggerBuilder('ready-test'))
            ->addRedis(LogLevel::INFO, $this->redisMockExpectingSetex(1))
            ->addRedisMq($this->redisMockExpectingPublish(1), 'logs')
            ->getLogger();

        $logger->info('ready handles');
    }

    // ---- provider errors reach the caller unchanged, nothing memoized -------------------------

    public function testLogRedisProviderExceptionPropagatesAndIsNotMemoized(): void
    {
        $calls = 0;
        $redis = $this->redisMockExpectingSetex(1);
        $handler = new LogRedis(LogLevel::INFO, function () use (&$calls, $redis): Redis {
            $calls++;
            if ($calls === 1) {
                throw new RuntimeException('connect refused');
            }

            return $redis;
        });

        try {
            $handler(LogLevel::INFO, 'first');
            $this->fail('provider exception must reach the caller');
        } catch (RuntimeException $e) {
            $this->assertSame('connect refused', $e->getMessage());
        }

        $this->assertNotNull($handler(LogLevel::INFO, 'second'));
        $this->assertSame(2, $calls);
    }

    public function testLogRedisProviderRedisExceptionIsNotSwallowedAsWriteFailure(): void
    {
        $handler = new LogRedis(LogLevel::INFO, function (): Redis {
            throw new RedisException('connection refused');
        });

        $this->expectException(RedisException::class);
        $handler(LogLevel::INFO, 'first');
    }

    public function testLogRedisMqProviderExceptionPropagatesDespiteSilentFailure(): void
    {
        $calls = 0;
        $redis = $this->redisMockExpectingPublish(1);
        $handler = new LogRedisMq(function () use (&$calls, $redis): Redis {
            $calls++;
            if ($calls === 1) {
                throw new RuntimeException('connect refused');
            }

            return $redis;
        }, 'logs');

        try {
            $handler(LogLevel::INFO, 'first');
            $this->fail('provider exception must reach the caller');
        } catch (RuntimeException $e) {
            $this->assertSame('connect refused', $e->getMessage());
        }

        $this->assertNotNull($handler(LogLevel::INFO, 'second'));
        $this->assertSame(2, $calls);
    }

    public function testProviderExceptionThroughLoggerReachesErrorHandler(): void
    {
        $seen = [];
        $logger = (new LoggerBuilder('err-test'))
            ->addRedisMq(function (): Redis {
                throw new RuntimeException('connect refused');
            }, 'logs', 'mq')
            ->setErrorHandler(function (\Exception $e) use (&$seen): void {
                $seen[] = $e->getMessage();
            })
            ->getLogger();

        $logger->info('entry');

        $this->assertSame(['connect refused'], $seen);
    }
}
