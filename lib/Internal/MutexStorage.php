<?php

namespace Amp\Sync\Internal;

use Amp\Sync\Lock;
use function Amp\delay;

class MutexStorage extends \Threaded
{
    private const LATENCY_TIMEOUT = 10;

    /** @var bool */
    private $locked = false;

    public function acquire(): Lock
    {
        $tsl = function () {
            if ($this->locked) {
                return true;
            }

            $this->locked = true;
            return false;
        };

        while ($this->locked || $this->synchronized($tsl)) {
            delay(self::LATENCY_TIMEOUT);
        }

        return new Lock(0, function () {
            $this->locked = false;
        });
    }
}
