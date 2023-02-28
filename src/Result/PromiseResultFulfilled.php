<?php

declare(strict_types=1);

namespace AsyncPromise\Result;

class PromiseResultFulfilled implements PromiseResultInterface
{
    public function __construct(public readonly string $status, public readonly string $value)
    {
    }
}
