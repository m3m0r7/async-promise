<?php

declare(strict_types=1);

namespace AsyncPromise\Resolver;

class PromiseResolver
{
    /**
     * @var callable
     */
    protected $resolver;

    public function __construct(callable $resolver)
    {
        $this->resolver = $resolver;
    }

    public function __invoke(mixed ...$parameters): void
    {
        ($this->resolver)(...$parameters);
    }
}
