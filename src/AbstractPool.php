<?php
namespace Icicle\Postgres;

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
     * @var \Icicle\Coroutine\Coroutine|null
     */
    private $coroutine;

    /**
     * @coroutine
     *
     * @return \Generator
     *
     * @resolve \Icicle\Postgres\Connection
     *
     * @throws \Icicle\Postgres\Exception\FailureException
     */
    abstract protected function createConnection();

    public function __construct()
    {
        $this->connections = new \SplObjectStorage();
        $this->idle = new \SplQueue();
        $this->busy = new \SplQueue();
    }

    /**
     * {@inheritdoc}
     */
    public function getConnectionCount()
    {
        return $this->connections->count();
    }

    /**
     * {@inheritdoc}
     */
    public function getIdleConnectionCount()
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

        $this->connections[$connection] = 0;
        $this->idle->push($connection);
    }

    /**
     * @coroutine
     *
     * @return \Generator
     *
     * @resolve \Icicle\Postgres\Connection
     */
    private function pop()
    {
        while (null !== $this->coroutine) {
            yield $this->coroutine; // Prevent simultaneous connection creation.
        }

        if ($this->idle->isEmpty()) {
            if ($this->connections->count() >= $this->getMaxConnections()) {
                // All possible connections busy, so shift from head (will be pushed back onto tail below).
                $connection = $this->busy->shift();
            } else {
                // Max connection count has not been reached, so open another connection.
                try {
                    $this->coroutine = new Coroutine($this->createConnection());
                    $connection = (yield $this->coroutine);
                    $this->connections[$connection] = 0;
                } finally {
                    $this->coroutine = null;
                }
            }
        } else {
            // Shift a worker off the idle queue.
            $connection = $this->idle->shift();
        }

        $this->busy->push($connection);
        $this->connections[$connection] += 1;

        yield $connection;
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

        if (0 < ($this->connections[$connection] -= 1)) {
            return;
        }

        // Connection is completely idle, remove from busy queue and add to idle queue.
        foreach ($this->busy as $key => $busy) {
            if ($busy === $connection) {
                unset($this->busy[$key]);
                break;
            }
        }

        $this->idle->push($connection);
    }

    /**
     * {@inheritdoc}
     */
    public function query($sql)
    {
        /** @var \Icicle\Postgres\Connection $connection */
        $connection = (yield $this->pop());

        try {
            $result = (yield $connection->query($sql));
        } finally {
            $this->push($connection);
        }

        yield $result;
    }

    /**
     * {@inheritdoc}
     */
    public function execute($sql, ...$params)
    {
        /** @var \Icicle\Postgres\Connection $connection */
        $connection = (yield $this->pop());

        try {
            $result = (yield $connection->execute($sql, ...$params));
        } finally {
            $this->push($connection);
        }

        yield $result;
    }

    /**
     * {@inheritdoc}
     */
    public function prepare($sql)
    {
        /** @var \Icicle\Postgres\Connection $connection */
        $connection = (yield $this->pop());

        try {
            $result = (yield $connection->prepare($sql));
        } finally {
            $this->push($connection);
        }

        yield $result;
    }
}
