<?php
namespace Icicle\Postgres;

use Icicle\Awaitable\Delayed;
use Icicle\Loop;
use Icicle\Loop\Watcher\Io;
use Icicle\Postgres\Exception\FailureException;
use Icicle\Postgres\Exception\QueryError;

class BasicConnection implements Connection
{
    /**
     * @var resource PostgreSQL connection handle.
     */
    private $handle;

    /**
     * @var \Icicle\Awaitable\Delayed|null
     */
    private $delayed;

    /**
     * @var \Icicle\Loop\Watcher\Io
     */
    private $poll;

    /**
     * @var \Icicle\Loop\Watcher\Io
     */
    private $await;

    /**
     * @var callable
     */
    private $executeCallback;

    /**
     * @var callable
     */
    private $onCancelled;

    /**
     * Connection constructor.
     *
     * @param resource $handle PostgreSQL connection handle.
     * @param resource $socket PostgreSQL connection stream socket.
     */
    public function __construct($handle, $socket)
    {
        $this->handle = $handle;

        $this->poll = Loop\poll($socket, static function ($resource, $expired, Io $poll) use ($handle) {
            /** @var \Icicle\Awaitable\Delayed $delayed */
            $delayed = $poll->getData();

            if (!\pg_consume_input($handle)) {
                $delayed->reject(new FailureException(\pg_last_error($handle)));
                return;
            }

            if (!\pg_connection_busy($handle)) {
                $delayed->resolve(\pg_get_result($handle));
                return;
            }

            $poll->listen(); // Reading not done, listen again.
        });

        $this->await = Loop\await($socket, static function ($resource, $expired, Io $await) use ($handle) {
            $flush = \pg_flush($handle);
            if (0 === $flush) {
                $await->listen(); // Not finished sending data, listen again.
                return;
            }

            if (false === $flush) {
                /** @var \Icicle\Awaitable\Delayed $delayed */
                $delayed = $await->getData();
                $delayed->reject(new FailureException(\pg_last_error($handle)));
            }
        });

        $this->onCancelled = static function () use ($handle) {
            \pg_cancel_query($handle);
        };

        $this->executeCallback = function ($name, array $params) {
            yield $this->createResult(yield $this->send('pg_send_execute', $name, $params));
        };
    }

    /**
     * Frees Io watchers from loop.
     */
    public function __destruct()
    {
        if (\is_resource($this->handle)) {
            \pg_close($this->handle);
        }

        $this->poll->free();
        $this->await->free();
    }

    /**
     * @coroutine
     *
     * @param callable $function Function name to execute.
     * @param mixed ...$args Arguments to pass to function.
     *
     * @return \Generator
     *
     * @resolve resource
     *
     * @throws \Icicle\Postgres\Exception\FailureException
     */
    private function send(callable $function, ...$args)
    {
        while (null !== $this->delayed) {
            try {
                yield $this->delayed;
            } catch (\Exception $exception) {
                // Ignore failure from another operation.
            }
        }

        $result = $function($this->handle, ...$args);

        if (false === $result) {
            throw new FailureException(\pg_last_error($this->handle));
        }

        $this->delayed = new Delayed($this->onCancelled);

        $this->poll->setData($this->delayed);
        $this->await->setData($this->delayed);

        $this->poll->listen();
        if (0 === $result) {
            $this->await->listen();
        }

        try {
            $result = (yield $this->delayed);
        } finally {
            $this->delayed = null;
            $this->poll->cancel();
            $this->await->cancel();
        }

        yield $result;
    }

    /**
     * @param resource $result PostgreSQL result resource.
     *
     * @return \Icicle\Postgres\CommandResult|\Icicle\Postgres\TupleResult
     *
     * @throws \Icicle\Postgres\Exception\FailureException
     */
    private function createResult($result)
    {
        switch (\pg_result_status($result, \PGSQL_STATUS_LONG)) {
            case \PGSQL_EMPTY_QUERY:
                throw new QueryError('Empty query string');

            case \PGSQL_COMMAND_OK:
                return new CommandResult($result);

            case \PGSQL_TUPLES_OK:
                return new TupleResult($result);

            case \PGSQL_NONFATAL_ERROR:
            case \PGSQL_FATAL_ERROR:
                throw new QueryError(\pg_result_error($result));

            case \PGSQL_BAD_RESPONSE:
                throw new FailureException(\pg_result_error($result));

            default:
                throw new FailureException('Unknown result status');
        }
    }

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
    public function query($sql)
    {
        yield $this->createResult(yield $this->send('pg_send_query', (string) $sql));
    }

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
    public function execute($sql, ...$params)
    {
        yield $this->createResult(yield $this->send('pg_send_query_params', (string) $sql, $params));
    }

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
    public function prepare($sql)
    {
        $sql = (string) $sql;

        if (!(yield $this->send('pg_send_prepare', $sql, $sql))) {
            throw new FailureException(\pg_last_error($this->handle));
        }

        yield new Statement($sql, $this->executeCallback);
    }
}
