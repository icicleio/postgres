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
    public function query($sql);

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
    public function execute($sql, ...$params);

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
    public function prepare($sql);
}
