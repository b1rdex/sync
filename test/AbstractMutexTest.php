<?php

namespace Amp\Sync\Test;

use Amp\Loop;
use Amp\PHPUnit\TestCase;
use Amp\Sync\Mutex;
use Concurrent\Task;

/**
 * @requires extension pthreads
 */
abstract class AbstractMutexTest extends TestCase
{
    abstract public function createMutex(): Mutex;

    public function testAcquire(): void
    {
        $mutex = $this->createMutex();
        $lock = $mutex->acquire();
        $lock->release();
        $this->assertTrue($lock->isReleased());
    }

    public function testAcquireMultiple(): void
    {
        $this->assertRunTimeGreaterThan(function () {
            Loop::defer(function () {
                Task::async(function () {
                    $mutex = $this->createMutex();

                    $lock1 = $mutex->acquire();
                    Loop::delay(100, function () use ($lock1) {
                        Task::async(function () use ($lock1) {
                            $lock1->release();
                        });
                    });

                    $lock2 = $mutex->acquire();
                    Loop::delay(100, function () use ($lock2) {
                        Task::async(function () use ($lock2) {
                            $lock2->release();
                        });
                    });

                    $lock3 = $mutex->acquire();
                    Loop::delay(100, function () use ($lock3) {
                        Task::async(function () use ($lock3) {
                            $lock3->release();
                        });
                    });
                });
            });
        }, 300);
    }
}
