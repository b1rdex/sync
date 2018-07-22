<?php

namespace Amp\Sync;

use Concurrent\Deferred;
use Concurrent\Task;

class LocalMutex implements Mutex
{
    /** @var bool */
    private $locked = false;

    /** @var Deferred[] */
    private $queue = [];

    /** @var callable */
    private $release;

    public function __construct()
    {
        $this->release = \Closure::fromCallable([$this, "release"]);
    }

    /** {@inheritdoc} */
    public function acquire(): Lock
    {
        if (!$this->locked) {
            $this->locked = true;

            return new Lock(0, $this->release);
        }

        $this->queue[] = $deferred = new Deferred;

        return Task::await($deferred->awaitable());
    }

    private function release(): void
    {
        if (!empty($this->queue)) {
            $deferred = \array_shift($this->queue);
            $deferred->resolve(new Lock(0, $this->release));

            return;
        }

        $this->locked = false;
    }
}
