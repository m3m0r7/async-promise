<?php

declare(strict_types=1);

namespace AsyncPromise\Driver;

use AsyncPromise\Exception\PromiseException;

class SwooleDriver extends AbstractDriver
{
    protected \Co\Channel $notify;

    public function __construct()
    {
        if (!extension_loaded('swoole') && !extension_loaded('openswoole')) {
            throw new PromiseException('The swoole or openswoole are not installed');
        }
    }

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

    public static function postAny(): void
    {
        // Inject Swoole coroutine bugs.
        usleep(1);
    }

    public static function postAll(): void
    {
        // Inject Swoole coroutine bugs.
        usleep(1);
    }

    public static function postAllSettled(): void
    {
        // Inject Swoole coroutine bugs.
        usleep(1);
    }

    public static function postRace(): void
    {
        // Inject Swoole coroutine bugs.
        usleep(1);
    }

    public static function createContext(callable $callback): void
    {
        \Co\run($callback);
    }
}
