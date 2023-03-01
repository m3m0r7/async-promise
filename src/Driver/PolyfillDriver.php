<?php

declare(strict_types=1);

namespace AsyncPromise\Driver;

class PolyfillDriver extends AbstractDriver
{
    public function async(callable $callback): void
    {
        $callback();
    }

    public function wait(): void
    {
    }

    public function notify(): void
    {
    }
}
