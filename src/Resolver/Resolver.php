<?php

declare(strict_types=1);

namespace AsyncPromise\Resolver;

use AsyncPromise\Promise;

class Resolver
{
    protected Promise $promise;
    public mixed $result;

    public function __construct(Promise $promise)
    {
        $this->promise = $promise;
        $this->result = null;
    }

    public function __invoke(callable $callback): callable
    {
        return new PromiseResolver(function (...$parameters) use ($callback) {
            $this->result = $parameters;
            $callback();
        });
    }
}
