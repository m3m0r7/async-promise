<?php

declare(strict_types=1);

namespace AsyncPromise\Driver;

use AsyncPromise\Exception\PromiseException;

class PcntlDriver extends AbstractDriver
{
    protected int|null $pid = null;

    public function __construct()
    {
        if (!extension_loaded('pcntl')) {
            throw new PromiseException('The pcntl is not installed');
        }
    }

    public function async(callable $callback): void
    {
        $this->pid = pcntl_fork();
        if ($this->pid === -1) {
            throw new PromiseException('The PcntlDriver cannot fork');
        }
        if ($this->pid === 0) {
            $callback();
        }
    }

    public function wait(): void
    {
        if ($this->pid === 0) {
            throw new PromiseException('Cannot wait in the child process. You must call wait method in the parent process.');
        }

        pcntl_waitpid($this->pid, $status);
        $exitCode = pcntl_wexitstatus($status);
        if ($exitCode !== 0) {
            throw new PromiseException("The PcntlDriver received invalid exit code: {$exitCode}");
        }
    }

    public function notify(): void
    {
        if ($this->pid > 0) {
            throw new PromiseException('Cannot notify in the parent process. You must call notify method in the child process.');
        }

    }
}
