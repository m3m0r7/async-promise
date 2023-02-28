<?php declare(strict_types=1);

namespace AsyncPromise\Tests;

use AsyncPromise\Driver\PolyfillDriver;
use AsyncPromise\Driver\SwooleDriver;
use AsyncPromise\Promise;
use PHPUnit\Framework\TestCase;

class PromiseTest extends TestCase
{
    /**
     * @dataProvider provideDrivers
     */
    public function testPromiseFulfilled(string $driverName)
    {
        Promise::setPromiseDriver($driverName);

        \Co\run(function () {
            $promise = new Promise(fn(callable $resolve) => $resolve(true));
            $this->assertInstanceOf(Promise::class, $promise);
            $this->assertSame(Promise::FULFILLED, $promise->status());
        });
    }

    /**
     * @dataProvider provideDrivers
     */
    public function testPromiseRejected(string $driverName)
    {
        Promise::setPromiseDriver($driverName);

        \Co\run(function () {
            $promise = new Promise(fn(callable $_, callable $reject) => $reject(true));
            $this->assertInstanceOf(Promise::class, $promise);
            $this->assertSame(Promise::REJECTED, $promise->status());
        });
    }

    public static function provideDrivers(): array
    {
        return [
            [SwooleDriver::class],
            [PolyfillDriver::class],
        ];
    }
}
