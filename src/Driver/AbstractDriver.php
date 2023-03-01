<?php

declare(strict_types=1);

namespace AsyncPromise\Driver;

use AsyncPromise\Exception\PromiseException;

abstract class AbstractDriver implements DriverInterface
{
    abstract public function async(callable $callback): void;
    abstract public function wait(): void;
    abstract public function notify(): void;

    public static function postAny(): void
    {
    }

    public static function postAll(): void
    {
    }

    public static function postAllSettled(): void
    {
    }

    public static function postRace(): void
    {
    }
}
