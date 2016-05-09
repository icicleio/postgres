<?php
namespace Icicle\Postgres;

interface Executor
{
    /**
     * @coroutine
     *
     * @param string $sql
     *
     * @return \Generator
     *
     * @resolve \Icicle\Postgres\Result
     *
     * @throws \Icicle\Postgres\Exception\FailureException
     */
    public function query(string $sql): \Generator;

    /**
     * @coroutine
     *
     * @param string $sql
     * @param mixed ...$params
     *
     * @return \Generator
     *
     * @resolve \Icicle\Postgres\Result
     *
     * @throws \Icicle\Postgres\Exception\FailureException
     */
    public function execute(string $sql, ...$params): \Generator;

    /**
     * @coroutine
     *
     * @param string $sql
     *
     * @return \Generator
     *
     * @resolve \Icicle\Postgres\Statement
     *
     * @throws \Icicle\Postgres\Exception\FailureException
     */
    public function prepare(string $sql): \Generator;
}
