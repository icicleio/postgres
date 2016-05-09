<?php
namespace Icicle\Postgres;

interface Connection extends Executor
{
    /**
     * @coroutine
     *
     * @param int $isolation
     *
     * @return \Generator
     *
     * @resolve \Icicle\Postgres\Transaction
     *
     * @throws \Icicle\Postgres\Exception\FailureException
     */
    public function transaction(int $isolation = Transaction::COMMITTED): \Generator;
}
