<?php

namespace Amp\Sync;

/**
 * An asynchronous semaphore based on pthreads' synchronization methods.
 *
 * This is an implementation of a thread-safe semaphore that has non-blocking
 * acquire methods. There is a small tradeoff for asynchronous semaphores; you
 * may not acquire a lock immediately when one is available and there may be a
 * small delay. However, the small delay will not block the thread.
 */
class ThreadedSemaphore implements Semaphore {
    /** @var \Threaded */
    private $semaphore;

    /**
     * Creates a new semaphore with a given number of locks.
     *
     * @param int $locks The maximum number of locks that can be acquired from the semaphore.
     *
     * @throws \Error If locks is smaller than `1`.
     */
    public function __construct(int $locks) {
        if ($locks < 1) {
            throw new \Error("The number of locks should be a positive integer");
        }

        $this->semaphore = new Internal\SemaphoreStorage($locks);
    }

    /**
     * {@inheritdoc}
     */
    public function acquire(): Lock {
        return $this->semaphore->acquire();
    }
}
