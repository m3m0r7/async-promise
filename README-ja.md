# AsyncPromise

## AsyncPromise とは

AsyncPromise は，PHP で JavaScript の Promise を参考に実装された非同期処理を簡易的に行うためのライブラリです。

## 必要要件

- PHP 8.1 以上
- Swoole (SwooleDriver を使用する場合)

## インストール方法

以下のコマンドを実行することでインストールができます。

```sh
$ composer require m3m0r7/async-promise
```

## 使い方

### クイックスタート

一般的には JavaScript と同様に以下のように使用することができます。

```php
<?php

use AsyncPromise\Promise;

(new Promise(function (callable $resolve) {
    $resolve('resolved!');
}))->then(function ($result) {
    // `resolved!` と表示します。
    echo $result;
});

```

例外は `catch` をチェインすることで処理が可能です。

```php
<?php

use AsyncPromise\Promise;

(new Promise(function (callable $resolve) {
    throw new Exception('An error occurred');
}))->catch(function ($reason) {
    // `An error occurred` と表示します。
    echo $reason;
});

```

もしくは以下のように `Promise` のコールバック関数に渡される第二引数で拒否することでも可能です。


```php
<?php

use AsyncPromise\Promise;

(new Promise(function (callable $_, callable $reject) {
    $reject('An error occurred');
}))->catch(function ($reason) {
    // `An error occurred` と表示します。
    echo $reason;
});

```

`then` を複数回チェインすることも可能です。

```php
<?php

use AsyncPromise\Promise;

(new Promise(function (callable $resolve) {
    $resolve('resolved!');
}))->then(function ($result) {
    return 'nested chain: ' . $result;
})->then(function ($result) {
    // `nested chain: resolved!` と表示します。
    echo $result;
});

```

`then` または `catch` 実行の後に，特定の処理を実行したい場合は `finally` を使用することができます。

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

渡された `Promise` で拒否されるまで全てを履行し，その結果を新しい Promise として返します。

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
    // 以下のような結果が出力されます。
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

拒否が実行される場合は以下のようになります。

```php
<?php

use AsyncPromise\Promise;

Promise::all([
    65535,
    'text',
    (new Promise(fn (callable $_, callable $reject) => $reject('rejected'))),
])->then(function (array $values) {
    // このステートメントには到達しません。
    print_r($values);
})->catch(function (string $reason) {
    // rejected と出力されます。
    echo $reason;
});

```

#### Promise::allSettled(array)

渡された `Promise` が全て履行または拒否されるまで実行し，その結果を新しい Promise として返します。


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
            // 以下が表示されます:
            //   fulfilled: 65535
            //   fulfilled: text
            //   fulfilled: resolved
            echo "{$value->status}: {$value->value}\n";
        }
        if ($value->status === Promise::REJECTED) {
            // 以下が表示されます:
            //   rejected: rejected
            echo "{$value->status}: {$value->reason}\n";
        }
    }
});

```


#### Promise::race(array)
#### Promise::any(array)
#### Promise::resolve(mixed)
#### Promise::reject(string)


### ドライバー

非同期処理のドライバーを選択することができます。現状では以下のドライバーが実装されています。

- \AsyncPromise\Driver\SwooleDriver
- \AsyncPromise\Driver\PolyfillDriver


ドライバーの切り替えは以下のように行います。

```php

Promise::setPromiseDriver(\AsyncPromise\Driver\SwooleDriver::class);

(new Promise(...))->then(...);

```

`SwooleDriver` を使用する場合，以下のように `\Co\run(...)` 関数のコンテキスト内で `Promise` を実行する必要があります。


```php

Promise::setPromiseDriver(\AsyncPromise\Driver\SwooleDriver::class);

\Co\run(function () {
    (new Promise(fn (callable $resolve) => $resolve('resolved with SwooleDriver')))
        // `resolved with SwooleDriver` と表示されます。
        ->then(fn ($result) => print($result));
});

```


SwooleDriver の恩恵を受けるのは，非同期に処理する場合です。以下のようなコードは SwooleDriver の本領を発揮します。


```php

Promise::setPromiseDriver(\AsyncPromise\Driver\SwooleDriver::class);

// sleep 関数をコルーチンに対応させます
\Swoole\Runtime::enableCoroutine(SWOOLE_HOOK_SLEEP);

\Co\run(function () {
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
        // Take 5 sec と表示されます。
        echo "Take " . (time() - $start) . " sec";
    });
});

```

`PolyfillDriver` は，非同期処理ライブラリが導入されていない場合に，導入されているように仮定して実行させるためのドライバーです。
実態は非同期ではなく同期的に動作するためパフォーマンスが向上することはありません。上記のコードを `PolyfillDriver` で実行した場合 `Take 8 sec` と表示されます。

## テストの実行

以下のコマンドでテストを実行できます。

```
./vendor/bin/phpunit tests/
```