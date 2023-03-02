<?php

declare(strict_types=1);

namespace AsyncPromise\Driver;

use AsyncPromise\Exception\PromiseException;
use AsyncPromise\Promise;

class PcntlDriver extends AbstractDriver
{
    protected const FULFILLED_RETURN_CODE = 127;
    protected const REJECTED_RETURN_CODE = 128;

    protected int|null $pid = null;
    protected Promise $promise;

    public function __construct(Promise $promise)
    {
        if (!extension_loaded('pcntl')) {
            throw new PromiseException('The pcntl is not installed');
        }

        $this->promise = $promise;
    }

    public function async(callable $callback): void
    {
        $this->pid = pcntl_fork();
        if ($this->pid === -1) {
            throw new PromiseException('The PcntlDriver cannot fork');
        }
        if ($this->pid === 0) {
            $status = $callback();

            // Stop chain forcibly and return status code to a parent process.
            exit(
                $status === Promise::FULFILLED
                    ? static::FULFILLED_RETURN_CODE
                    : static::REJECTED_RETURN_CODE
            );
        }
    }

    public function wait(): void
    {
        if ($this->pid === 0) {
            throw new PromiseException('Cannot wait in the child process. You must call wait method in the parent process.');
        }

        pcntl_waitpid($this->pid, $status);
        $exitCode = pcntl_wexitstatus($status);

        match ($exitCode) {
            static::FULFILLED_RETURN_CODE => $this->promise->setStatus(Promise::FULFILLED),
            static::REJECTED_RETURN_CODE => $this->promise->setStatus(Promise::REJECTED),
            default => throw new PromiseException("The PcntlDriver received invalid exit code: {$exitCode}"),
        };
    }

    public function notify(): void
    {
        if ($this->pid > 0) {
            throw new PromiseException('Cannot notify in the parent process. You must call notify method in the child process.');
        }
    }
}
