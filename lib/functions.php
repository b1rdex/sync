<?php

namespace Amp\Sync;

use Concurrent\Awaitable;
use Concurrent\Task;

/**
 * Invokes the given callback while maintaining a lock from the provided mutex. The lock is automatically released after
 * invoking the callback or once the callback returned. If the callback returns an Awaitable, it will be awaited.
 *
 * @param Mutex    $mutex
 * @param callable $callback
 * @param array    ...$args
 *
 * @return mixed Return value of the callback.
 */
function synchronized(Mutex $mutex, callable $callback, ...$args)
{
    $lock = $mutex->acquire();

    try {
        $returnValue = $callback(...$args);
        if ($returnValue instanceof Awaitable) {
            $returnValue = Task::await($returnValue);
        }

        return $returnValue;
    } finally {
        $lock->release();
    }
}
