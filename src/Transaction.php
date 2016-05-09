<?php
namespace Icicle\Postgres;

use Icicle\Postgres\Exception\TransactionError;

class Transaction implements Executor
{
    const UNCOMMITTED  = 0;
    const COMMITTED    = 1;
    const REPEATABLE   = 2;
    const SERIALIZABLE = 4;

    /**
     * @var \Icicle\Postgres\Executor
     */
    private $executor;

    /**
     * @var callable|null
     */
    private $push;

    /**
     * @param \Icicle\Postgres\Executor $executor
     * @param callable|null $push
     */
    public function __construct(Executor $executor, callable $push = null)
    {
        $this->executor = $executor;
        $this->push = $push;
    }

    public function __destruct()
    {
        if (null !== $this->push) {
            $this->push();
        }
    }

    /**
     * @return bool
     */
    public function isActive()
    {
        return null !== $this->executor;
    }

    /**
     * Calls the push function given to the constructor.
     */
    private function push()
    {
        if (null !== $this->push) {
            $push = $this->push;
            $push();

            $this->push = null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function query($sql)
    {
        if (null === $this->executor) {
            throw new TransactionError('The transaction has been committed or rolled back');
        }

        yield $this->executor->query($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function prepare($sql)
    {
        if (null === $this->executor) {
            throw new TransactionError('The transaction has been committed or rolled back');
        }

        yield $this->executor->prepare($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function execute($sql, ...$params)
    {
        if (null === $this->executor) {
            throw new TransactionError('The transaction has been committed or rolled back');
        }

        yield $this->executor->execute($sql, ...$params);
    }

    /**
     * @coroutine
     *
     * Commits the transaction and makes it inactive.
     *
     * @return \Generator
     *
     * @resolve \Icicle\Postgres\CommandResult
     *
     * @throws \Icicle\Postgres\Exception\TransactionError
     */
    public function commit()
    {
        if (null === $this->executor) {
            throw new TransactionError('The transaction has been committed or rolled back');
        }

        $executor = $this->executor;
        $this->executor = null;

        try {
            yield $executor->query('COMMIT');
        } finally {
            $this->push();
        }
    }

    /**
     * @coroutine
     *
     * Rolls back the transaction and makes it inactive.
     *
     * @return \Generator
     *
     * @resolve \Icicle\Postgres\CommandResult
     *
     * @throws \Icicle\Postgres\Exception\TransactionError
     */
    public function rollback()
    {
        if (null === $this->executor) {
            throw new TransactionError('The transaction has been committed or rolled back');
        }

        $executor = $this->executor;
        $this->executor = null;

        try {
            yield $executor->query('ROLLBACK');
        } finally {
            $this->push();
        }
    }

    /**
     * @coroutine
     *
     * Creates a savepoint with the given identifier. WARNING: Identifier is not sanitized, do not pass untrusted data.
     *
     * @return \Generator
     *
     * @resolve \Icicle\Postgres\CommandResult
     *
     * @throws \Icicle\Postgres\Exception\TransactionError
     */
    public function savepoint($identifier)
    {
        return $this->query('SAVEPOINT ' . $identifier);
    }

    /**
     * @coroutine
     *
     * Rolls back to the savepoint with the given identifier. WARNING: Identifier is not sanitized, do not pass
     * untrusted data.
     *
     * @return \Generator
     *
     * @resolve \Icicle\Postgres\CommandResult
     *
     * @throws \Icicle\Postgres\Exception\TransactionError
     */
    public function rollbackTo($identifier)
    {
        return $this->query('ROLLBACK TO ' . $identifier);
    }

    /**
     * @coroutine
     *
     * Releases the savepoint with the given identifier. WARNING: Identifier is not sanitized, do not pass untrusted
     * data.
     *
     * @return \Generator
     *
     * @resolve \Icicle\Postgres\CommandResult
     *
     * @throws \Icicle\Postgres\Exception\TransactionError
     */
    public function release($identifier)
    {
        return $this->query('RELEASE SAVEPOINT ' . $identifier);
    }
}
