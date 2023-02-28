<?php

declare(strict_types=1);

namespace AsyncPromise\Driver;

class SwooleDriver implements DriverInterface
{
    protected \Co\Channel $notify;

    public function async(callable $callback): void
    {
        go(function () use ($callback) {
            $this->notify = new \Co\Channel(1);
            $callback();
            $this->notify->close();
        });
    }

    public function wait(): void
    {
        $this->notify->pop();
    }

    public function notify(): void
    {
        $this->notify->push(1);
    }
}
