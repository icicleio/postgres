<?php
namespace Icicle\Postgres;

use Icicle\Awaitable\Delayed;
use Icicle\Awaitable\Exception\TimeoutException;
use Icicle\Loop;
use Icicle\Postgres\Exception\FailureException;

if (!\function_exists(__NAMESPACE__ . '\connect')) {
    /**
     * @coroutine
     *
     * @param string $connectionString
     * @param float|int $timeout
     *
     * @return \Generator
     *
     * @resolve \Icicle\Postgres\Connection
     *
     * @throws \Icicle\Postgres\Exception\FailureException
     */
    function connect($connectionString, $timeout = 0)
    {
        if (!$connection = @\pg_connect($connectionString, \PGSQL_CONNECT_ASYNC | \PGSQL_CONNECT_FORCE_NEW)) {
            throw new FailureException('Failed to create connection resource');
        }

        if (\pg_connection_status($connection) === \PGSQL_CONNECTION_BAD) {
            throw new FailureException(\pg_last_error($connection));
        }

        if (!$socket = \pg_socket($connection)) {
            throw new FailureException('Failed to access connection socket');
        }

        $delayed = new Delayed();

        $callback = function ($resource, $expired) use (&$poll, &$await, $connection, $delayed, $timeout) {
            try {
                if ($expired) {
                    throw new TimeoutException('Connection attempt timed out.');
                }

                switch (\pg_connect_poll($connection)) {
                    case \PGSQL_POLLING_READING:
                        return; // Connection not ready, poll again.

                    case \PGSQL_POLLING_WRITING:
                        $await->listen($timeout);
                        return; // Still writing...

                    case \PGSQL_POLLING_FAILED:
                        throw new FailureException('Could not connect to PostgreSQL server');

                    case \PGSQL_POLLING_OK:
                        $poll->free();
                        $await->free();
                        $delayed->resolve(new BasicConnection($connection, $resource));
                        return;
                }
            } catch (\Exception $exception) {
                $poll->free();
                $await->free();
                \pg_close($connection);
                $delayed->reject($exception);
            }
        };

        $poll = Loop\poll($socket, $callback, true);
        $await = Loop\await($socket, $callback);

        $poll->listen($timeout);
        $await->listen($timeout);

        yield $delayed;
    }

    /**
     * @param string $connectionString
     * @param int $maxConnections
     * @param float|int $connectTimeout
     *
     * @return \Icicle\Postgres\ConnectionPool
     */
    function pool(
        $connectionString,
        $maxConnections = ConnectionPool::DEFAULT_MAX_CONNECTIONS,
        $connectTimeout = ConnectionPool::DEFAULT_CONNECT_TIMEOUT
    ) {
        return new ConnectionPool($connectionString, $maxConnections, $connectTimeout);
    }
}
