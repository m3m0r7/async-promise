<?php

declare(strict_types=1);

namespace AsyncPromise\Driver;

interface DriverInterface
{
    public function async(callable $callback): void;
    public function wait(): void;
    public function notify(): void;
}
