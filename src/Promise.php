<?php

declare(strict_types=1);

namespace AsyncPromise;

use AsyncPromise\Driver\DriverInterface;
use AsyncPromise\Driver\PolyfillDriver;
use AsyncPromise\Driver\SwooleDriver;
use AsyncPromise\Exception\HandlePropagator;
use AsyncPromise\Exception\PromiseException;
use AsyncPromise\Result\PromiseResultFulfilled;
use AsyncPromise\Result\PromiseResultRejected;
use AsyncPromise\Resolver\Resolver;

/** @phpstan-consistent-constructor */
class Promise
{
    public const FULFILLED = 'fulfilled';
    public const PENDING = 'pending';
    public const REJECTED = 'rejected';

    protected const THEN = 2;
    protected const CATCH = 4;
    protected const FINALLY = 8;

    protected Resolver $fulfilled;
    protected Resolver $rejected;
    protected static ?string $driverName = null;
    protected string $status = self::PENDING;

    /**
     * @var callable $function
     */
    protected $function;
    protected DriverInterface $driver;

    public static function setPromiseDriver(string $driver): void
    {
        static::$driverName = $driver;
    }

    public static function setPromiseDriverAutomatically(): void
    {
        // Set driver automatically when not calling setPromiseDriver function
        if (static::$driverName !== null) {
            return;
        }

        if (extension_loaded('swoole') || extension_loaded('openswoole')) {
            static::setPromiseDriver(SwooleDriver::class);
            return;
        }
        static::setPromiseDriver(PolyfillDriver::class);
    }

    public function __construct(callable $function)
    {
        static::setPromiseDriverAutomatically();

        $this->fulfilled = new Resolver($this);
        $this->rejected = new Resolver($this);

        $this->start(
            $function,
            false,
            ($this->fulfilled)(function ($value) {
                $this->status = static::FULFILLED;
            }),
            ($this->rejected)(function ($value) {
                $this->status = static::REJECTED;
            }),
        );
    }

    public static function resolve(mixed $value): self
    {
        return new static(function (callable $resolve) use ($value) {
            $resolve($value);
        });
    }

    public static function reject(mixed $value): self
    {
        return new static(function (callable $_, callable $reject) use ($value) {
            $reject($value);
        });
    }

    protected function start(callable $function, bool $useReturnValue, mixed ...$parameters): self
    {
        $this->driver = new (static::$driverName)();
        $this->function = $function;

        $this->driver->async(function () use ($parameters, $useReturnValue) {
            $result = null;
            try {
                $result = ($this->function)(...$parameters);
                if ($result instanceof Promise) {
                    $this->fulfilled->result = [$result->fulfilled->result];
                    $this->rejected->result = [$result->rejected->result];
                } elseif ($useReturnValue) {
                    if ($this->status === self::FULFILLED) {
                        $this->fulfilled->result = [$result];
                    } elseif ($this->status === self::REJECTED) {
                        $this->rejected->result = [$result];
                    }
                }
            } catch (\Throwable $e) {
                $this->status = static::REJECTED;
                $this->rejected->result = [$e->getMessage()];
            }
            $this->driver->notify();
        });

        return $this;
    }

    /**
     * @param array<mixed> $promises
     */
    public static function all(array $promises): self
    {
        return new static(function (callable $resolve, callable $reject) use ($promises) {
            $remaining = count($promises);
            $results = [];
            do {
                foreach ($promises as $index => $promise) {
                    if ($promise instanceof Promise) {
                        if ($promise->status === static::FULFILLED) {
                            $remaining--;
                            $results[$index] = is_array($promise->fulfilled->result)
                                ? current($promise->fulfilled->result)
                                : $promise->fulfilled->result;
                        }
                        if ($promise->status === static::REJECTED) {
                            if (is_array($promise->rejected->result)) {
                                $reject(...$promise->rejected->result);
                            } else {
                                $reject($promise->rejected->result);
                            }
                            return;
                        }
                    } else {
                        $remaining--;
                        $results[$index] = $promise;
                    }
                }

                if (static::$driverName !== null) {
                    (static::$driverName)::postAll();
                }
            } while ($remaining > 0);
            $resolve(array_values($results));
        });
    }

    /**
     * @param array<mixed> $promises
     */
    public static function allSettled(array $promises): self
    {
        return new static(function (callable $resolve, callable $reject) use ($promises) {
            $remaining = count($promises);
            $results = [];
            do {
                foreach ($promises as $index => $promise) {
                    if ($promise instanceof Promise) {
                        if ($promise->status === static::FULFILLED) {
                            $remaining--;
                            $results[$index] = new PromiseResultFulfilled(
                                $promise->status,
                                is_array($promise->fulfilled->result)
                                    ? current($promise->fulfilled->result)
                                    : $promise->fulfilled->result
                            );
                        } elseif ($promise->status === static::REJECTED) {
                            $remaining--;
                            $results[$index] = new PromiseResultRejected(
                                $promise->status,
                                is_array($promise->rejected->result)
                                    ? current($promise->rejected->result)
                                    : $promise->rejected->result,
                            );
                        }
                    } else {
                        $remaining--;
                        $results[$index] = new PromiseResultFulfilled(
                            static::FULFILLED,
                            $promise
                        );
                    }
                }

                if (static::$driverName !== null) {
                    (static::$driverName)::postAllSettled();
                }
            } while ($remaining > 0);

            $resolve(array_values($results));
        });
    }

    /**
     * @param array<mixed> $promises
     */
    public static function race(array $promises): self
    {
        return new static(function (callable $resolve, callable $reject) use ($promises) {
            do {
                foreach ($promises as $index => $promise) {
                    if ($promise instanceof Promise) {
                        if ($promise->status === static::FULFILLED) {
                            if (is_array($promise->fulfilled->result)) {
                                $resolve(...$promise->fulfilled->result);
                            } else {
                                $resolve($promise->fulfilled->result);
                            }
                            return;
                        }
                        if ($promise->status === static::REJECTED) {
                            if (is_array($promise->rejected->result)) {
                                $reject(...$promise->rejected->result);
                            } else {
                                $reject($promise->rejected->result);
                            }
                            return;
                        }
                    } else {
                        $resolve($promise);
                        return;
                    }
                }

                if (static::$driverName !== null) {
                    (static::$driverName)::postRace();
                }
            } while (true);
        });
    }

    /**
     * @param array<mixed> $promises
     */
    public static function any(array $promises): self
    {
        return new static(function (callable $resolve, callable $reject) use ($promises) {
            $remaining = count($promises);
            $results = [];
            do {
                foreach ($promises as $index => $promise) {
                    if ($promise instanceof Promise) {
                        if ($promise->status === static::FULFILLED) {
                            if (is_array($promise->fulfilled->result)) {
                                $resolve(...$promise->fulfilled->result);
                            } else {
                                $resolve($promise->fulfilled->result);
                            }
                            return;
                        }

                        if ($promise->status === static::REJECTED) {
                            $remaining--;
                            $results[$index] = is_array($promise->rejected->result)
                                ? current($promise->rejected->result)
                                : $promise->rejected->result;
                        }
                    } else {
                        $resolve($promise);
                        return;
                    }
                }

                if (static::$driverName !== null) {
                    (static::$driverName)::postAny();
                }
            } while ($remaining > 0);

            $reject(array_values($results));
        });
    }

    protected function __clone()
    {
        $this->status = self::PENDING;
        $this->fulfilled = new Resolver($this);
        $this->rejected = new Resolver($this);
    }

    public function then(callable $callback, callable $onRejected = null): self
    {
        $this->driver->wait();

        if ($onRejected === null) {
            $onRejected = fn (string $reason) => throw new HandlePropagator($reason);
        }

        $newPromise = clone $this;
        $newPromise->status = static::FULFILLED;

        if ($this->status === static::FULFILLED) {
            return $newPromise
                ->start(
                    $callback,
                    true,
                    ...(is_array($this->fulfilled->result)
                        ? $this->fulfilled->result
                        : [$this->fulfilled->result]
                    )
                );
        }

        return $this->_catch($newPromise, $onRejected);
    }

    public function catch(callable $callback): self
    {
        $this->driver->wait();

        $newPromise = clone $this;
        $newPromise->status = static::FULFILLED;
        return $this->_catch($newPromise, $callback);
    }

    protected function _catch(Promise $newPromise, callable $callback): self
    {
        $rejectedReason = is_array($this->rejected->result)
            ? $this->rejected->result
            : [$this->rejected->result];

        if ($rejectedReason === [null]) {
            return $newPromise;
        }

        return $newPromise
            ->start(
                $callback,
                true,
                ...(is_array($this->rejected->result)
                ? $this->rejected->result
                : [$this->rejected->result]
                )
            );
    }

    public function finally(callable $callback): self
    {
        $this->driver->wait();

        $newPromise = clone $this;
        $newPromise->status = static::FULFILLED;

        return $newPromise
            ->start($callback, true);
    }

    public function status(): string
    {
        return $this->status;
    }
}
