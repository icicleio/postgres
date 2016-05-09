<?php
namespace Icicle\Postgres;

use Icicle\Awaitable;
use Icicle\Awaitable\Delayed;
use Icicle\Coroutine\Coroutine;
use Icicle\Exception\InvalidArgumentError;

abstract class AbstractPool implements Pool
{
    /**
     * @var \SplQueue
     */
    private $idle;

    /**
     * @var \SplQueue
     */
    private $busy;

    /**
     * @var \SplObjectStorage
     */
    private $connections;

    /**
     * @var \Icicle\Awaitable\Awaitable|null
     */
    private $awaitable;

    /**
     * @coroutine
     *
     * @return \Generator
     *
     * @resolve \Icicle\Postgres\Connection
     *
     * @throws \Icicle\Postgres\Exception\FailureException
     */
    abstract protected function createConnection(): \Generator;

    public function __construct()
    {
        $this->connections = new \SplObjectStorage();
        $this->idle = new \SplQueue();
        $this->busy = new \SplQueue();
    }

    /**
     * {@inheritdoc}
     */
    public function getConnectionCount(): int
    {
        return $this->connections->count();
    }

    /**
     * {@inheritdoc}
     */
    public function getIdleConnectionCount(): int
    {
        return $this->idle->count();
    }

    /**
     * @param \Icicle\Postgres\Connection $connection
     */
    protected function addConnection(Connection $connection)
    {
        if (isset($this->connections[$connection])) {
            return;
        }

        $this->connections->attach($connection);
        $this->idle->push($connection);
    }

    /**
     * @coroutine
     *
     * @return \Generator
     *
     * @resolve \Icicle\Postgres\Connection
     */
    private function pop(): \Generator
    {
        while (null !== $this->awaitable) {
            try {
                yield $this->awaitable; // Prevent simultaneous connection creation.
            } catch (\Throwable $exception) {
                // Ignore failure or cancellation of other operations.
            }
        }

        if ($this->idle->isEmpty()) {
            try {
                if ($this->connections->count() >= $this->getMaxConnections()) {
                    // All possible connections busy, so wait until one becomes available.
                    $this->awaitable = new Delayed();
                    yield $this->awaitable;
                } else {
                    // Max connection count has not been reached, so open another connection.
                    $this->awaitable = new Coroutine($this->createConnection());
                    $this->addConnection(yield $this->awaitable);
                }
            } finally {
                $this->awaitable = null;
            }
        }

        // Shift a connection off the idle queue.
        return $this->idle->shift();
    }

    /**
     * @param \Icicle\Postgres\Connection $connection
     *
     * @throws \Icicle\Exception\InvalidArgumentError
     */
    private function push(Connection $connection)
    {
        if (!isset($this->connections[$connection])) {
            throw new InvalidArgumentError('Connection is not part of this pool');
        }

        $this->idle->push($connection);

        if ($this->awaitable instanceof Delayed) {
            $this->awaitable->resolve($connection);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function query(string $sql): \Generator
    {
        /** @var \Icicle\Postgres\Connection $connection */
        $connection = yield from $this->pop();

        try {
            $result = yield from $connection->query($sql);
        } finally {
            $this->push($connection);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(string $sql, ...$params): \Generator
    {
        /** @var \Icicle\Postgres\Connection $connection */
        $connection = yield from $this->pop();

        try {
            $result = yield from $connection->execute($sql, ...$params);
        } finally {
            $this->push($connection);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function prepare(string $sql): \Generator
    {
        /** @var \Icicle\Postgres\Connection $connection */
        $connection = yield from $this->pop();

        try {
            $result = yield from $connection->prepare($sql);
        } finally {
            $this->push($connection);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function transaction(int $isolation = Transaction::COMMITTED): \Generator
    {
        /** @var \Icicle\Postgres\Connection $connection */
        $connection = yield from $this->pop();

        try {
            $transaction = yield from $connection->transaction($isolation);
        } catch (\Throwable $exception) {
            $this->push($connection);
            throw $exception;
        }

        return new Transaction($transaction, $isolation, function () use ($connection) {
            $this->push($connection);
        });
    }
}
