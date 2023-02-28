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

    protected ?Promise $nextPromise = null;
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
            ($this->fulfilled)(fn() => $this->status = static::FULFILLED),
            ($this->rejected)(fn() => $this->status = static::REJECTED),
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

    protected function start(callable $function, mixed ...$parameters): void
    {
        $this->driver = new (static::$driverName)();
        $this->function = $function;

        $this->driver->async(function () use ($parameters) {
            $result = null;
            try {
                $result = ($this->function)(...$parameters);
                if ($result instanceof Promise) {
                    $this->nextPromise = $result;
                } elseif ($result === null) {
                    $this->nextPromise = $this->createNoop();
                } else {
                    $this->nextPromise = clone $this;
                    $this->nextPromise->fulfilled->result = [$result];
                }
            } catch (\Throwable $e) {
                $this->nextPromise = clone $this;
                $this->nextPromise->rejected->result = [$e->getMessage()];
            }

            $this->driver->notify();
        });
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
                            // stop to loop
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
            } while ($remaining > 0);

            $reject(array_values($results));
        });
    }

    protected function __clone()
    {
        $this->fulfilled = clone $this->fulfilled;
        $this->rejected = clone $this->rejected;
    }

    private function createNoop(): self
    {
        $newPromise = clone $this;
        $newPromise->fulfilled->result ??= [null];
        $newPromise->rejected->result ??= [null];
        return $newPromise;
    }

    public function then(callable $callback, callable $onRejected = null): self
    {
        if ($onRejected === null) {
            $onRejected = fn (string $reason) => throw new HandlePropagator($reason);
        }

        $this->catch($onRejected);

        return $this->process(
            $callback,
            fn (Promise $promise) => $promise->fulfilled->result,
        );
    }

    public function catch(callable $callback): self
    {
        return $this->process(
            $callback,
            fn (Promise $promise) => $promise->rejected->result,
        );
    }

    public function finally(callable $callback): self
    {
        return $this->process($callback, fn () => []);
    }

    protected function process(callable $callback, callable $extractArguments): self
    {
        $this->driver->wait();

        if (!($this->nextPromise instanceof Promise)) {
            throw new PromiseException('The promise is not started');
        }

        $newPromise = clone $this->nextPromise;
        $result = $extractArguments($newPromise);

        if ($result !== null) {
            $newPromise->start($callback, ...$result);
        }

        $newPromise->nextPromise ??= $this->createNoop();
        return $newPromise;
    }

    public function status(): string
    {
        return $this->status;
    }
}
