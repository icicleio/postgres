#!/usr/bin/env php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Icicle\Postgres;

Icicle\execute(function () {
    /** @var \Icicle\Postgres\Connection $connection */
    $connection = yield from Postgres\connect('host=localhost user=postgres');

    /** @var \Icicle\Postgres\Statement $statement */
    $statement = yield from $connection->prepare('SHOW ALL');

    /** @var \Icicle\Postgres\TupleResult $result */
    $result = yield from $statement->execute();

    $iterator = $result->getIterator();

    while (yield from $iterator->isValid()) {
        $row = $iterator->getCurrent();
        \printf("%-35s = %s (%s)\n", $row['name'], $row['setting'], $row['description']);
    }
});
