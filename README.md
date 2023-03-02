English | [日本語](./README-ja.md)

---

# AsyncPromise

## What is the AsyncPromise?

The AsyncPromise is a library for processing concurrently on PHP which implementing similarity with Javascript's Promise.

## Requirements

- PHP 8.1 or higher
- Swoole (if you use SwooleDriver)

## How to install

You can install as following command:

```sh
$ composer require m3m0r7/async-promise
```

## How to use

### Get started

The Promise can be used in the same way JavaScript as following:

```php
<?php

use AsyncPromise\Promise;

(new Promise(function (callable $resolve) {
    $resolve('resolved!');
}))->then(function ($result) {
    // Show `resolved!`
    echo $result;
});

```

You can receive an exception to chain with `catch` method.

```php
<?php

use AsyncPromise\Promise;

(new Promise(function (callable $resolve) {
    throw new Exception('An error occurred');
}))->catch(function ($reason) {
    // Show `An error occurred`
    echo $reason;
});

```

Or you can process a rejection by the second parameter on a Promise callback function.


```php
<?php

use AsyncPromise\Promise;

(new Promise(function (callable $_, callable $reject) {
    $reject('An error occurred');
}))->catch(function ($reason) {
    // Show `An error occurred`
    echo $reason;
});

```

And the `then` method can be multiple chained.

```php
<?php

use AsyncPromise\Promise;

(new Promise(function (callable $resolve) {
    $resolve('resolved!');
}))->then(function ($result) {
    return 'nested chain: ' . $result;
})->then(function ($result) {
    // Show `nested chain: resolved!`
    echo $result;
});

```

After calling `then` or `catch` method, you can use `finally` if you want to run something.

```php
<?php

use AsyncPromise\Promise;

(new Promise(function (callable $resolve) {
    $resolve('resolved!');
}))->then(function ($result) {
    return 'nested chain: ' . $result;
}, function ($reason) {
    echo $reason;
})->finally(function ($result) {
    echo "Finally was reached";
});

```

#### Promise::all(array)
The result is returned as a new Promise when all of the passed `Promises` are fulfilled until rejecting.

```php
<?php

use AsyncPromise\Promise;

Promise::all([
    65535,
    'text',
    (new Promise(fn (callable $resolve) => $resolve('fulfilled1'))),
    ['key' => 'value'],
    (new Promise(fn (callable $resolve) => $resolve('fulfilled2'))),
])->then(function (array $values) {
    // Show as following:
    //
    // Array
    // (
    //    [0] => 65535
    //    [1] => text
    //    [2] => fulfilled1
    //    [3] => Array
    //        (
    //            [key] => value
    //        )
    //
    //    [4] => fulfilled2
    // )
    print_r($values);
});

```

It will run until reject as following:

```php
<?php

use AsyncPromise\Promise;

Promise::all([
    65535,
    'text',
    (new Promise(fn (callable $_, callable $reject) => $reject('rejected'))),
])->then(function (array $values) {
    // This statement is unreachable.
    print_r($values);
})->catch(function (string $reason) {
    // Show `rejected`
    echo $reason;
});

```

#### Promise::allSettled(array)

The result is returned as a new Promise when all of the passed `Promises` are processed.


```php
<?php

use AsyncPromise\Promise;

Promise::allSettled([
    65535,
    'text',
    (new Promise(fn (callable $resolve) => $resolve('resolved'))),
    (new Promise(fn (callable $_, callable $reject) => $reject('rejected'))),
])->then(function (array $values) {
    foreach ($values as $value) {
        if ($value->status === Promise::FULFILLED) {
            // Show as following:
            //   fulfilled: 65535
            //   fulfilled: text
            //   fulfilled: resolved
            echo "{$value->status}: {$value->value}\n";
        }
        if ($value->status === Promise::REJECTED) {
            // Show as following:
            //   rejected: rejected
            echo "{$value->status}: {$value->reason}\n";
        }
    }
});

```


#### Promise::race(array)


The result is returned as a new Promise when one of the passed `Promises` is processed.


```php
<?php

use AsyncPromise\Promise;

Promise::race([
    (new Promise(fn (callable $resolve) => $resolve('resolved1'))),
    (new Promise(fn (callable $_, callable $reject) => $reject('rejected1'))),
    (new Promise(fn (callable $resolve) => $resolve('resolved2'))),
    (new Promise(fn (callable $_, callable $reject) => $reject('rejected2'))),
])->then(function ($value) {
    // Show `resolved1`
    echo "{$value}\n";
});

```

#### Promise::any(array)

The result is returned as a new Promise when one of the passed `Promises` is fulfilled.


```php
<?php

use AsyncPromise\Promise;

Promise::any([
    (new Promise(fn (callable $resolve) => $resolve('resolved1'))),
    (new Promise(fn (callable $_, callable $reject) => $reject('rejected1'))),
    (new Promise(fn (callable $resolve) => $resolve('resolved2'))),
    (new Promise(fn (callable $_, callable $reject) => $reject('rejected2'))),
])->then(function ($value) {
    // Show `resolved1`
    echo "{$value}\n";
});

```

And it is not fulfilled, it will chain to `catch` method.

```php
<?php

use AsyncPromise\Promise;

Promise::any([
    (new Promise(fn (callable $_, callable $reject) => $reject('rejected1'))),
    (new Promise(fn (callable $_, callable $reject) => $reject('rejected2'))),
    (new Promise(fn (callable $_, callable $reject) => $reject('rejected3'))),
    (new Promise(fn (callable $_, callable $reject) => $reject('rejected4'))),
])->catch(function (array $values) {
    // Show as following:
    //
    // Array
    // (
    //     [0] => rejected1
    //     [1] => rejected2
    //     [2] => rejected3
    //     [3] => rejected4
    // )
    print_r($values);
});

```

#### Promise::resolve(mixed)

It will resolve `Promise`.

```php
<?php

use AsyncPromise\Promise;

Promise::resolve('resolved1')
    ->then(function (string $value) {
        // Show `resolved1`
        echo "resolved1\n";
    });
```

It will reject `Promise`.

```php
<?php

use AsyncPromise\Promise;

Promise::reject('resolved1')
    ->catch(function (string $value) {
        // Show `resolved1`
        echo "resolved1\n";
    });
```

#### Promise::reject(string)


### Drivers

You can choose to run concurrently driver. The AsyncPromise was implemented as following:

- \AsyncPromise\Driver\SwooleDriver
- \AsyncPromise\Driver\FiberDriver
- \AsyncPromise\Driver\PcntlDriver (experimental)
- \AsyncPromise\Driver\PolyfillDriver


To switch other driver:

```php

Promise::setPromiseDriver(\AsyncPromise\Driver\SwooleDriver::class);

(new Promise(...))->then(...);

```

You must run a Promise in `Promise::createContext(...)` context if you use the `SwooleDriver`.


```php

Promise::setPromiseDriver(\AsyncPromise\Driver\SwooleDriver::class);

Promise::createContext(function () {
    (new Promise(fn (callable $resolve) => $resolve('resolved with SwooleDriver')))
        // Show `resolved with SwooleDriver`
        ->then(fn ($result) => print($result));
});

```


You will get a benefit with concurrency when using `SwooleDriver`. The below command is getting SwooleDriver performance:

```php

Promise::setPromiseDriver(\AsyncPromise\Driver\SwooleDriver::class);

// sleep function to be coroutinized.
\Swoole\Runtime::enableCoroutine(SWOOLE_HOOK_SLEEP);

Promise::createContext(function () {
    $start = time();
    Promise::all([
        new Promise(function (callable $resolve) {
            sleep(3);
            $resolve();
        }),
        new Promise(function (callable $resolve) {
            sleep(5);
            $resolve();
        }),
    ])->then(function ($values) use ($start) {
        // Show `Take 5 sec`
        echo "Take " . (time() - $start) . " sec";
    });
});

```

The `PolyfillDriver` is a virtual process driver when concurrency driver is not installed.
Therefore the `PolyfillDriver` does not increase performance because which is running on synchronization. For example, to run above code; it will show `Take 8 sec`.

## How to test

You can run tests as following commands:

```
./vendor/bin/phpunit tests/
```