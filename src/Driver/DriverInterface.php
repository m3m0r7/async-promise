<?php

declare(strict_types=1);

namespace AsyncPromise\Driver;

interface DriverInterface
{
    public function async(callable $callback): void;
    public function wait(): void;
    public function notify(): void;

    public static function postAny(): void;
    public static function postAll(): void;
    public static function postAllSettled(): void;
    public static function postRace(): void;
}
