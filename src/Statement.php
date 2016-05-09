<?php
namespace Icicle\Postgres;

class Statement
{
    /**
     * @var string
     */
    private $sql;

    /**
     * @var callable
     */
    private $execute;

    /**
     * @param string $sql
     * @param callable $execute
     */
    public function __construct(string $sql, callable $execute)
    {
        $this->sql = $sql;
        $this->execute = $execute;
    }

    /**
     * @return string
     */
    public function getQuery(): string
    {
        return $this->sql;
    }

    /**
     * @coroutine
     *
     * @param mixed ...$params
     *
     * @return \Generator
     *
     * @resolve \Icicle\Postgres\Result
     *
     * @throws \Icicle\Postgres\Exception\FailureException If executing the statement fails.
     */
    public function execute(...$params): \Generator
    {
        return ($this->execute)($this->sql, $params);
    }
}