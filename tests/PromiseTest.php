<?php

declare(strict_types=1);

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


    /**
     * @dataProvider provideDrivers
     */
    public function testPromiseThen(string $driverName)
    {
        Promise::setPromiseDriver($driverName);

        \Co\run(function () {
            (new Promise(fn(callable $resolve) => $resolve('test')))
                ->then(function ($result) {
                    $this->assertSame('test', $result);
                });
        });
    }

    /**
     * @dataProvider provideDrivers
     */
    public function testPromiseThenWithCatch(string $driverName)
    {
        Promise::setPromiseDriver($driverName);

        \Co\run(function () {
            (new Promise(fn(callable $resolve) => $resolve('test')))
                ->then(function ($result) {
                    $this->assertSame('test', $result);
                }, function ($result) {
                    $this->assertFalse(true);
                });
        });
    }


    /**
     * @dataProvider provideDrivers
     */
    public function testPromiseThenWithCatchCallingRejecting(string $driverName)
    {
        Promise::setPromiseDriver($driverName);

        \Co\run(function () {
            (new Promise(fn(callable $_, callable $reject) => $reject('test')))
                ->then(function ($result) {
                    $this->assertFalse(true);
                }, function ($result) {
                    $this->assertSame('test', $result);
                });
        });
    }

    /**
     * @dataProvider provideDrivers
     */
    public function testPromiseChainingThen(string $driverName)
    {
        Promise::setPromiseDriver($driverName);

        $results = [];
        \Co\run(function () use (&$results) {
            (new Promise(fn(callable $resolve) => $resolve('test')))
                ->then(function ($result) use (&$results) {
                    $results[] = 'then1';
                    return 'then2';
                })
                ->then(function ($received) use (&$results) {
                    $results[] = $received;
                });
        });
        $this->assertSame([
            'then1',
            'then2',
        ], $results);
    }


    /**
     * @dataProvider provideDrivers
     */
    public function testPromiseChainingCatch(string $driverName)
    {
        Promise::setPromiseDriver($driverName);

        $results = [];
        \Co\run(function () use (&$results) {
            (new Promise(fn(callable $_, callable $reject) => $reject('test')))
                ->catch(function ($result) use (&$results) {
                    $results[] = 'then1';
                    throw new \Exception('then2');
                })
                ->catch(function ($reason) use (&$results) {
                    $results[] = $reason;
                });
        });
        $this->assertSame([
            'then1',
            'then2',
        ], $results);
    }

    /**
     * @dataProvider provideDrivers
     */
    public function testPromiseChainingThenToCatch(string $driverName)
    {
        Promise::setPromiseDriver($driverName);

        $results = [];
        \Co\run(function () use (&$results) {
            (new Promise(fn(callable $resolve) => $resolve('test')))
                ->then(function ($result) use (&$results) {
                    $results[] = 'then1';
                    throw new \Exception('then2');
                })
                ->catch(function ($reason) use (&$results) {
                    $results[] = $reason;
                });
        });
        $this->assertSame([
            'then1',
            'then2',
        ], $results);
    }

    /**
     * @dataProvider provideDrivers
     */
    public function testPromiseChainingCatchToThen(string $driverName)
    {
        Promise::setPromiseDriver($driverName);

        $results = [];
        \Co\run(function () use (&$results) {
            (new Promise(fn(callable $_, callable $reject) => $reject('then1')))
                ->catch(function ($reason) use (&$results) {
                    $results[] = $reason;
                    return 'then2';
                })->then(function ($result) use (&$results) {
                    $results[] = $result;
                });
        });
        $this->assertSame([
            'then1',
            'then2',
        ], $results);
    }

    /**
     * @dataProvider provideDrivers
     */
    public function testPromiseCatch(string $driverName)
    {
        Promise::setPromiseDriver($driverName);

        \Co\run(function () {
            (new Promise(fn(callable $_, callable $reject) => $reject('test')))
                ->catch(function ($reason) {
                    $this->assertSame('test', $reason);
                });
        });
    }

    /**
     * @dataProvider provideDrivers
     */
    public function testPromiseChainingCatchToThenNoReturn(string $driverName)
    {
        Promise::setPromiseDriver($driverName);

        $results = [];
        \Co\run(function () use (&$results) {
            (new Promise(fn(callable $_, callable $reject) => $reject('then1')))
                ->catch(function ($reason) use (&$results) {
                    $results[] = $reason;
                })->then(function ($result) use (&$results) {
                    $results[] = $result;
                });
        });
        $this->assertSame([
            'then1',
            null,
        ], $results);
    }

    /**
     * @dataProvider provideDrivers
     */
    public function testPromiseCallsStaticResolver(string $driverName)
    {
        Promise::setPromiseDriver($driverName);

        $results = [];
        \Co\run(function () use (&$results) {
            $promise = Promise::resolve('then1');

            $this->assertInstanceOf(Promise::class, $promise);
            $this->assertSame(Promise::FULFILLED, $promise->status());

            $promise->then(function ($result) use (&$results) {
                $results[] = $result;
            });
        });
        $this->assertSame([
            'then1',
        ], $results);
    }

    /**
     * @dataProvider provideDrivers
     */
    public function testPromiseCallsStaticRejecter(string $driverName)
    {
        Promise::setPromiseDriver($driverName);

        $results = [];
        \Co\run(function () use (&$results) {
            $promise = Promise::reject('then1');

            $this->assertInstanceOf(Promise::class, $promise);
            $this->assertSame(Promise::REJECTED, $promise->status());

            $promise->catch(function ($result) use (&$results) {
                $results[] = $result;
            });
        });
        $this->assertSame([
            'then1',
        ], $results);
    }

    /**
     * @dataProvider provideDrivers
     */
    public function testPromiseAll(string $driverName)
    {
        Promise::setPromiseDriver($driverName);

        $results = [];
        $instantiatedStdClass = null;
        \Co\run(function () use (&$results, &$instantiatedStdClass) {
            Promise::all([
                43,
                ['array'],
                $instantiatedStdClass = new \stdClass(),
                new Promise(fn (callable $resolve) => $resolve('fulfilled')),
                1.1,
                'string'
            ])->then(function ($values) use (&$results) {
                $results = $values;
            });
        });

        $this->assertSame([
            43,
            ['array'],
            $instantiatedStdClass,
            'fulfilled',
            1.1,
            'string',
        ], $results);
    }

    /**
     * @dataProvider provideDrivers
     */
    public function testPromiseAllRejecting(string $driverName)
    {
        Promise::setPromiseDriver($driverName);

        $results = [];
        $instantiatedStdClass = null;
        \Co\run(function () use (&$results, &$instantiatedStdClass) {
            Promise::all([
                43,
                ['array'],
                $instantiatedStdClass = new \stdClass(),
                new Promise(fn (callable $_, callable $reject) => $reject('rejected')),
                1.1,
                'string'
            ])->then(function ($values) use (&$results) {
                $results = $values;
            })->catch(function ($reason) use (&$results) {
                $results[] = $reason;
            });
        });

        $this->assertSame(['rejected'], $results);
    }

    /**
     * @dataProvider provideDrivers
     */
    public function testPromiseAllSettled(string $driverName)
    {
        Promise::setPromiseDriver($driverName);

        $results = [];
        $instantiatedStdClass = null;
        \Co\run(function () use (&$results, &$instantiatedStdClass) {
            Promise::allSettled([
                43,
                ['array'],
                $instantiatedStdClass = new \stdClass(),
                new Promise(fn (callable $resolve) => $resolve('fulfilled')),
                1.1,
                'string',
                new Promise(fn (callable $_, callable $reject) => $reject('rejected')),
            ])->then(function ($values) use (&$results) {
                foreach ($values as $result) {
                    $results[] = $result->value ?? $result->reason;
                }
            });
        });

        $this->assertSame([
            43,
            ['array'],
            $instantiatedStdClass,
            'fulfilled',
            1.1,
            'string',
            'rejected'
        ], $results);
    }

    /**
     * @dataProvider provideDrivers
     */
    public function testPromiseRace(string $driverName)
    {
        Promise::setPromiseDriver($driverName);

        $results = [];
        $instantiatedStdClass = null;
        \Co\run(function () use (&$results, &$instantiatedStdClass) {
            Promise::race([
                43,
                ['array'],
                $instantiatedStdClass = new \stdClass(),
                new Promise(fn (callable $resolve) => $resolve('fulfilled')),
                1.1,
                'string',
                new Promise(fn (callable $_, callable $reject) => $reject('rejected')),
            ])->then(function ($result) use (&$results) {
                $results = $result;
            });
        });

        $this->assertSame(43, $results);
    }

    /**
     * @dataProvider provideDrivers
     */
    public function testPromiseAny(string $driverName)
    {
        Promise::setPromiseDriver($driverName);

        $results = [];
        \Co\run(function () use (&$results, &$instantiatedStdClass) {
            Promise::any([
                new Promise(fn (callable $_, callable $reject) => $reject('rejected1')),
                new Promise(fn (callable $_, callable $reject) => $reject('rejected2')),
                new Promise(fn (callable $_, callable $reject) => $reject('rejected3')),
            ])->catch(function ($values) use (&$results) {
                $results = $values;
            });
        });

        $this->assertSame([
            'rejected1',
            'rejected2',
            'rejected3',
        ], $results);
    }

    /**
     * @dataProvider provideDrivers
     */
    public function testPromiseThrowAnException(string $driverName)
    {
        Promise::setPromiseDriver($driverName);

        $results = [];
        \Co\run(function () use (&$results) {
            (new Promise(function () {
                throw new \Exception('Throw an exception');
            }))->catch(function ($reason) use (&$results) {
                $results[] = $reason;
            });
        });

        $this->assertSame(['Throw an exception'], $results);
    }

    /**
     * @dataProvider provideDrivers
     */
    public function testPromiseThrowAnExceptionPassthroughThen(string $driverName)
    {
        Promise::setPromiseDriver($driverName);

        $results = [];
        \Co\run(function () use (&$results) {
            (new Promise(function () {
                throw new \Exception('Throw an exception');
            }))->then(function () {
                // Unreachable here
                $this->assertFalse(true);
            })->catch(function ($reason) use (&$results) {
                $results[] = $reason;
            });
        });

        $this->assertSame(['Throw an exception'], $results);
    }

    /**
     * @dataProvider provideDrivers
     */
    public function testPromiseThrowAnExceptionThenCatcher(string $driverName)
    {
        Promise::setPromiseDriver($driverName);

        $results = [];
        \Co\run(function () use (&$results) {
            (new Promise(function () {
                throw new \Exception('Throw an exception');
            }))->then(function () {
                $this->assertFalse(true);
            }, function ($reason) use (&$results) {
                $results[] = $reason . ' in the then';
            })->catch(function ($reason) use (&$results) {
                // Unreachable here
                $results[] = $reason;
            });
        });

        $this->assertSame(['Throw an exception in the then'], $results);
    }

    public static function provideDrivers(): array
    {
        return [
            [SwooleDriver::class],
            [PolyfillDriver::class],
        ];
    }
}
