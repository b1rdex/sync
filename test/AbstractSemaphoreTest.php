<?php

namespace Amp\Sync\Test;

use Amp\Loop;
use Amp\PHPUnit\TestCase;
use Amp\Sync\Semaphore;
use Concurrent\Deferred;
use Concurrent\Task;
use Concurrent\TaskScheduler;

abstract class AbstractSemaphoreTest extends TestCase
{
    /**
     * @var Semaphore
     */
    protected $semaphore;

    /**
     * @param int $locks Number of locks in the semaphore.
     *
     * @return Semaphore
     */
    abstract public function createSemaphore(int $locks): Semaphore;

    public function tearDown()
    {
        $this->semaphore = null; // Force Semaphore::__destruct() to be invoked.
    }

    public function testConstructorOnInvalidMaxLocks(): void
    {
        $this->expectException(\Error::class);

        $this->semaphore = $this->createSemaphore(-1);
    }

    public function testAcquire(): void
    {
        $this->semaphore = $this->createSemaphore(1);

        $lock = $this->semaphore->acquire();
        $this->assertFalse($lock->isReleased());

        $lock->release();
        $this->assertTrue($lock->isReleased());
    }

    public function testAcquireMultipleFromSingleLockSemaphore(): void
    {
        $this->assertRunTimeGreaterThan(function () {
            Loop::defer(function () {
                Task::async(function () {
                    $this->semaphore = $this->createSemaphore(1);
                    $deferreds = [];

                    $lock1 = $this->semaphore->acquire();
                    $deferreds[] = $deferred = new Deferred;
                    $this->assertSame(0, $lock1->getId());
                    Loop::delay(100, function () use ($lock1, $deferred) {
                        Task::async(function () use ($lock1, $deferred) {
                            $lock1->release();
                            $deferred->resolve();
                        });
                    });

                    $lock2 = $this->semaphore->acquire();
                    $deferreds[] = $deferred = new Deferred;
                    $this->assertSame(0, $lock2->getId());
                    Loop::delay(100, function () use ($lock2, $deferred) {
                        Task::async(function () use ($lock2, $deferred) {
                            $lock2->release();
                            $deferred->resolve();
                        });
                    });

                    $lock3 = $this->semaphore->acquire();
                    $this->assertSame(0, $lock3->getId());
                    Loop::delay(100, function () use ($lock3) {
                        Task::async(function () use ($lock3) {
                            $lock3->release();
                        });
                    });

                    foreach ($deferreds as $deferred) {
                        Task::await($deferred->awaitable());
                    }

                    $this->assertTrue($lock1->isReleased());
                    $this->assertTrue($lock2->isReleased());
                });
            });
        }, 300);
    }

    public function testAcquireMultipleFromMultipleLockSemaphore(): void
    {
        $this->assertRunTimeGreaterThan(function () {
            $this->semaphore = $this->createSemaphore(3);

            Loop::run(function () {
                $lock1 = $this->semaphore->acquire();
                Loop::delay(100, function () use ($lock1) {
                    Task::async(function () use ($lock1) {
                        $lock1->release();
                    });
                });

                $lock2 = $this->semaphore->acquire();
                $this->assertNotSame($lock1->getId(), $lock2->getId());
                Loop::delay(200, function () use ($lock2) {
                    Task::async(function () use ($lock2) {
                        $lock2->release();
                    });
                });

                $lock3 = $this->semaphore->acquire();
                $this->assertNotSame($lock1->getId(), $lock3->getId());
                $this->assertNotSame($lock2->getId(), $lock3->getId());
                Loop::delay(200, function () use ($lock3) {
                    Task::async(function () use ($lock3) {
                        $lock3->release();
                    });
                });

                $lock4 = $this->semaphore->acquire();
                $this->assertSame($lock1->getId(), $lock4->getId());
                Loop::delay(200, function () use ($lock4) {
                    Task::async(function () use ($lock4) {
                        $lock4->release();
                    });
                });
            });
        }, 300);
    }

    public function getSemaphoreSizes(): array
    {
        return [
            [5],
            [10],
            [20],
            [30],
        ];
    }

    /**
     * @dataProvider getSemaphoreSizes
     *
     * @param int $count Number of locks to test.
     */
    public function testAcquireFromMultipleSizeSemaphores(int $count): void
    {
        $this->assertRunTimeGreaterThan(function () use ($count) {
            $this->semaphore = $this->createSemaphore($count);

            foreach (\range(0, $count - 1) as $value) {
                Task::async(function () {
                    $lock = $this->semaphore->acquire();

                    Loop::delay(100, function () use ($lock) {
                        Task::async([$lock, "release"]);
                    });
                });
            }

            $lock = $this->semaphore->acquire();
            Loop::delay(100, function () use ($lock) {
                Task::async([$lock, "release"]);
            });
        }, 200);
    }

    public function testSimultaneousAcquire(): void
    {
        $this->assertRunTimeGreaterThan(function () {
            $this->semaphore = $this->createSemaphore(1);

            $awaitable1 = Task::async([$this->semaphore, 'acquire']);
            $awaitable2 = Task::async([$this->semaphore, 'acquire']);

            Loop::delay(100, function () use ($awaitable1) {
                Task::async(function () use ($awaitable1) {
                    Task::await($awaitable1)->release();
                });
            });

            Task::await($awaitable2)->release();
        }, 100);
    }
}
