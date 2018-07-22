<?php

namespace Amp\Sync\Test;

use Amp\PHPUnit\TestCase;
use Amp\Sync\LocalMutex;
use Concurrent\Task;
use function Amp\delay;
use function Amp\Sync\synchronized;
use function Concurrent\all;

class SynchronizedTest extends TestCase
{
    public function testSynchronized(): void
    {
        $this->assertRunTimeGreaterThan(function () {
            $mutex = new LocalMutex;
            $callback = function (int $value) {
                delay(100);

                return $value;
            };

            $awaitables = [];
            foreach (\range(0, 2) as $value) {
                $awaitables[] = Task::async(function () use ($mutex, $callback, $value) {
                    return synchronized($mutex, $callback, $value);
                });
            }

            foreach ($awaitables as $key => $awaitable) {
                $awaitables[$key] = Task::await($awaitable);
            }
            // TODO: Re-enable once Deferred::combine is fixed: $result = Task::await(all($awaitables));
            $this->assertSame(\range(0, 2), $awaitables);
        }, 300);
    }
}
