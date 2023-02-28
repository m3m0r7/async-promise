<?php

declare(strict_types=1);

namespace AsyncPromise\Resolver;

class PromiseResolver
{
    protected mixed $resolver;

    public function __construct(callable $resolver)
    {
        $this->resolver = $resolver;
    }

    public function __invoke(...$parameters): void
    {
        ($this->resolver)(...$parameters);
    }
}
