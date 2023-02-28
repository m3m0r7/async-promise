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



#### Promise::all(array)
#### Promise::allSettled(array)
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


`PolyfillDriver` は，非同期処理ライブラリが導入されていない場合に，導入されているように仮定して実行させるためのドライバーです。
実態は非同期ではなく同期的に動作するためパフォーマンスが向上することはありません。
