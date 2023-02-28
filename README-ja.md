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

### Promise::all
### Promise::allSettled
### Promise::race
### Promise::any
