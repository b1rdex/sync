<?php

namespace Amp\Sync\Test;

use Amp\Loop;
use Amp\Sync\Mutex;
use Amp\Sync\ThreadedMutex;
use Concurrent\Task;
use function Amp\delay;

/**
 * @requires extension pthreads
 */
class ThreadedMutexTest extends AbstractMutexTest
{
    public function createMutex(): Mutex
    {
        return new ThreadedMutex;
    }

    public function testWithinThread(): void
    {
        $mutex = $this->createMutex();

        $thread = new class($mutex) extends \Thread
        {
            private $mutex;

            public function __construct(Mutex $mutex)
            {
                $this->mutex = $mutex;
            }

            public function run(): void
            {
                // Protect scope by using an unbound closure (protects static access as well).
                (static function () {
                    $paths = [
                        \dirname(__DIR__) . \DIRECTORY_SEPARATOR . "vendor" . \DIRECTORY_SEPARATOR . "autoload.php",
                        \dirname(__DIR__, 3) . \DIRECTORY_SEPARATOR . "autoload.php",
                    ];

                    foreach ($paths as $path) {
                        if (\file_exists($path)) {
                            $autoloadPath = $path;
                            break;
                        }
                    }

                    if (!isset($autoloadPath)) {
                        throw new \Error("Could not locate autoload.php");
                    }

                    require $autoloadPath;
                })->bindTo(null, null)();

                $lock = $this->mutex->acquire();
                Loop::delay(1000, function () use ($lock) {
                    Task::async([$lock, "release"]);
                });
            }
        };

        $this->assertRunTimeGreaterThan(function () use ($mutex, $thread) {
            $thread->start(\PTHREADS_INHERIT_INI);

            delay(500); // Wait for thread to start and obtain lock.

            $lock = $mutex->acquire();
            Loop::delay(100, function () use ($lock) {
                Task::async([$lock, "release"]);
            });
        }, 1100);
    }
}
