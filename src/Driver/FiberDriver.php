<?php

declare(strict_types=1);

namespace AsyncPromise\Driver;

class FiberDriver extends AbstractDriver
{
    protected ?\Fiber $fiber = null;
    protected bool $notified = false;

    public function async(callable $callback): void
    {
        $this->fiber = new \Fiber($callback);
        $this->fiber->start();
    }

    public function wait(): void
    {
        while (!$this->notified) {
            usleep(1);
        };
    }

    public function notify(): void
    {
        $this->notified = true;
    }
}
