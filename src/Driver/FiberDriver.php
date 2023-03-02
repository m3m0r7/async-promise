<?php

declare(strict_types=1);

namespace AsyncPromise\Driver;

use AsyncPromise\Exception\PromiseException;

class FiberDriver extends AbstractDriver
{
    protected ?\Fiber $fiber = null;
    protected bool $notified = false;

    public function __construct()
    {
        if (!class_exists('Fiber', false)) {
            throw new PromiseException('The Fiber is not installed');
        }
    }

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
