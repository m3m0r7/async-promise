<?php declare(strict_types=1);
namespace AsyncPromise;

use AsyncPromise\Driver\DriverInterface;
use AsyncPromise\Exception\HandlePropagator;
use AsyncPromise\Result\PromiseResultFulfilled;
use AsyncPromise\Result\PromiseResultRejected;
use AsyncPromise\Rsolver\Resolver;

class Promise
{
    public const FULFILLED = 'fulfilled';
    public const PENDING = 'pending';
    public const REJECTED = 'rejected';

    protected ?Promise $nextPromise = null;
    protected Resolver $fulfilled;
    protected Resolver $rejected;
    protected static string $driverName;
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

    public function __construct(callable $function)
    {
        $this->fulfilled = new Resolver($this);
        $this->rejected = new Resolver($this);
        $this->start(
            $function,
            ($this->fulfilled)(fn() => $this->status = static::FULFILLED),
            ($this->rejected)(fn() => $this->status = static::REJECTED),
        );
    }

    protected function start(callable $function, ...$parameters): void
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
                $this->nextPromise->rejected->result = $e->getMessage();
            }

            $this->driver->notify();
        });
    }


    public static function all(array $promises): self
    {
        return new static(function (callable $resolve, callable $reject) use ($promises) {
            $remaining = count($promises);
            $results = [];
            do {
                /**
                 * @var Promise[] $promises
                 */
                foreach ($promises as $index => $promise) {
                    if ($promise->status === static::FULFILLED) {
                        $remaining--;
                        $results[$index] = $promise->fulfilled->result;
                    }
                    if ($promise->status === static::REJECTED) {
                        // stop to loop
                        $reject($promise->rejected->result);
                        return;
                    }
                }
            } while ($remaining > 0);

            // Sort result
            sort($results);
            $resolve(array_values($results));
        });
    }

    public static function allSettled(array $promises): self
    {
        return new static(function (callable $resolve, callable $reject) use ($promises) {
            $remaining = count($promises);
            $results = [];
            do {
                /**
                 * @var Promise[] $promises
                 */
                foreach ($promises as $index => $promise) {
                    if ($promise->status === static::FULFILLED) {
                        $remaining--;
                        $results[$index] = new PromiseResultFulfilled(
                            $promise->status,
                            $promise->fulfilled->result
                        );
                    } elseif ($promise->status === static::REJECTED) {
                        $remaining--;
                        $results[$index] = new PromiseResultRejected(
                            $promise->status,
                            $promise->rejected->result
                        );
                    }
                }
            } while ($remaining > 0);

            // Sort result
            sort($results);
            $resolve(array_values($results));
        });
    }

    public static function race(array $promises): self
    {
        return new static(function (callable $resolve, callable $reject) use ($promises) {
            do {
                /**
                 * @var Promise[] $promises
                 */
                foreach ($promises as $index => $promise) {
                    if ($promise->status === static::FULFILLED) {
                        $resolve($promise->fulfilled->result);
                        return;
                    }
                    if ($promise->status === static::REJECTED) {
                        // stop to loop
                        $reject($promise->rejected->result);
                        return;
                    }
                }
            } while (true);
        });
    }

    public static function any(array $promises): self
    {
        return new static(function (callable $resolve, callable $reject) use ($promises) {
            $remaining = count($promises);
            $results = [];
            do {
                /**
                 * @var Promise[] $promises
                 */
                foreach ($promises as $index => $promise) {
                    if ($promise->status === static::FULFILLED) {
                        // stop to loop
                        $resolve($promise->fulfilled->result);
                        return;
                    }

                    if ($promise->status === static::REJECTED) {
                        $remaining--;
                        $results[$index] = $promise->rejected->result;
                    }
                }
            } while ($remaining > 0);

            // Sort result
            sort($results);
            $reject(array_values($results));
        });
    }

    protected function __clone()
    {
        $this->fulfilled = clone $this->fulfilled;
        $this->rejected = clone $this->rejected;
    }

    protected function createNoop(): self
    {
        return clone $this;
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

        $newPromise = clone $this->nextPromise;
        $result = $extractArguments($newPromise);

        if ($result !== null) {
            $newPromise->start($callback, ...$result);
        }

        $newPromise->nextPromise ??= $this->createNoop();
        return $newPromise;
    }
}
