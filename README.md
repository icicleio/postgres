# PostgreSQL Client for Icicle

This library is a component for [Icicle](https://github.com/icicleio/icicle) that provides an asynchronous client for PostgreSQL. Like other Icicle components, this library uses [Coroutines](https://icicle.io/docs/manual/coroutines/) built from [Awaitables](https://icicle.io/docs/manual/awaitables/) and [Generators](http://www.php.net/manual/en/language.generators.overview.php) to make writing asynchronous code more like writing synchronous code.

[![Build Status](https://img.shields.io/travis/icicleio/postgres/v1.x.svg?style=flat-square)](https://travis-ci.org/icicleio/postgres)
[![Coverage Status](https://img.shields.io/coveralls/icicleio/postgres/v1.x.svg?style=flat-square)](https://coveralls.io/r/icicleio/postgres)
[![Semantic Version](https://img.shields.io/github/release/icicleio/postgres.svg?style=flat-square)](http://semver.org)
[![MIT License](https://img.shields.io/packagist/l/icicleio/postgres.svg?style=flat-square)](LICENSE)
[![@icicleio on Twitter](https://img.shields.io/badge/twitter-%40icicleio-5189c7.svg?style=flat-square)](https://twitter.com/icicleio)

#### Documentation and Support

- [Full API Documentation](https://icicle.io/docs)
- [Official Twitter](https://twitter.com/icicleio)
- [Gitter Chat](https://gitter.im/icicleio/icicle)

##### Requirements

- PHP 5.5+ for v0.1.x branch
- PHP 7 for v1.0 (v1.x branch and master) supporting generator delegation and return expressions

##### Installation

The recommended way to install is with the [Composer](http://getcomposer.org/) package manager. (See the [Composer installation guide](https://getcomposer.org/doc/00-intro.md) for information on installing and using Composer.)

Run the following command to use this library in your project: 

```bash
composer require icicleio/postgres
```

You can also manually edit `composer.json` to add this library as a project requirement.

```js
// composer.json
{
    "require": {
        "icicleio/postgres": "^0.1"
    }
}
```

#### Example

Note that this example uses the PHP 7+ only v1.x (master) branch.

```php
#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

use Icicle\Postgres;

Icicle\execute(function () {
    /** @var \Icicle\Postgres\Connection $connection */
    $connection = yield from Postgres\connect('host=localhost user=postgres dbname=test');

    /** @var \Icicle\Postgres\Statement $statement */
    $statement = yield from $connection->prepare('SELECT * FROM test WHERE id=$1');

    /** @var \Icicle\Postgres\TupleResult $result */
    $result = yield from $statement->execute(1337);

    $iterator = $result->getIterator();

    while (yield from $iterator->isValid()) {
        $row = $iterator->getCurrent();
        // $row is an array (map) of column values. e.g.: $row['column_name']
    }
});
```