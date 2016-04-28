<?php
namespace Icicle\Postgres;

class CommandResult
{
    /**
     * @var resource PostgreSQL result resource.
     */
    private $handle;

    /**
     * @param resource $handle PostgreSQL result resource.
     */
    public function __construct($handle)
    {
        $this->handle = $handle;
    }

    /**
     * Frees the result resource.
     */
    public function __destruct()
    {
        \pg_free_result($this->handle);
    }

    /**
     * @return string
     */
    public function lastOid()
    {
        return (string) \pg_last_oid($this->handle);
    }
}