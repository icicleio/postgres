<?php
namespace Icicle\Postgres;

use Icicle\Exception\InvalidArgumentError;
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
     * @var int
     */
    private $isolation;

    /**
     * @param \Icicle\Postgres\Executor $executor
     * @param int $isolation
     * @param callable|null $push
     *
     * @throws \Icicle\Exception\InvalidArgumentError
     */
    public function __construct(Executor $executor, int $isolation = self::COMMITTED, callable $push = null)
    {
        switch ($isolation) {
            case self::UNCOMMITTED:
            case self::COMMITTED:
            case self::REPEATABLE:
            case self::SERIALIZABLE:
                $this->isolation = $isolation;
                break;

            default:
                throw new InvalidArgumentError('$isolation must be a valid transaction isolation level');
        }

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
    public function isActive(): bool
    {
        return null !== $this->executor;
    }

    /**
     * @return int
     */
    public function getIsolationLevel(): int
    {
        return $this->isolation;
    }

    /**
     * Calls the push function given to the constructor.
     */
    private function push()
    {
        if (null !== $this->push) {
            ($this->push)();
            $this->push = null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function query(string $sql): \Generator
    {
        if (null === $this->executor) {
            throw new TransactionError('The transaction has been committed or rolled back');
        }

        return yield from $this->executor->query($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function prepare(string $sql): \Generator
    {
        if (null === $this->executor) {
            throw new TransactionError('The transaction has been committed or rolled back');
        }

        return yield from $this->executor->prepare($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function execute(string $sql, ...$params): \Generator
    {
        if (null === $this->executor) {
            throw new TransactionError('The transaction has been committed or rolled back');
        }

        return yield from $this->executor->execute($sql, ...$params);
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
    public function commit(): \Generator
    {
        if (null === $this->executor) {
            throw new TransactionError('The transaction has been committed or rolled back');
        }

        $executor = $this->executor;
        $this->executor = null;

        try {
            $result = yield from $executor->query('COMMIT');
        } finally {
            $this->push();
        }

        return $result;
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
    public function rollback(): \Generator
    {
        if (null === $this->executor) {
            throw new TransactionError('The transaction has been committed or rolled back');
        }

        $executor = $this->executor;
        $this->executor = null;

        try {
            $result = yield from $executor->query('ROLLBACK');
        } finally {
            $this->push();
        }

        return $result;
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
    public function savepoint(string $identifier): \Generator
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
    public function rollbackTo(string $identifier): \Generator
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
    public function release(string $identifier): \Generator
    {
        return $this->query('RELEASE SAVEPOINT ' . $identifier);
    }
}
