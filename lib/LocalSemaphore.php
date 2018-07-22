<?php

namespace Amp\Sync;

use Concurrent\Deferred;
use Concurrent\Task;

class LocalSemaphore implements Semaphore
{
    /** @var int[] */
    private $locks;

    /** @var Deferred[] */
    private $queue = [];

    /** @var callable */
    private $release;

    public function __construct(int $maxLocks)
    {
        if ($maxLocks < 1) {
            throw new \Error("The number of locks must be greater than 0");
        }

        $this->release = \Closure::fromCallable([$this, "release"]);
        $this->locks = \range(0, $maxLocks - 1);
    }

    /** {@inheritdoc} */
    public function acquire(): Lock
    {
        if (!empty($this->locks)) {
            return new Lock(\array_shift($this->locks), $this->release);
        }

        $this->queue[] = $deferred = new Deferred;

        return Task::await($deferred->awaitable());
    }

    private function release(Lock $lock): void
    {
        $id = $lock->getId();

        if (!empty($this->queue)) {
            $deferred = \array_shift($this->queue);
            $deferred->resolve(new Lock($id, $this->release));

            return;
        }

        $this->locks[] = $id;
    }
}
