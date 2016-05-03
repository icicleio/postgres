<?php
namespace Icicle\Postgres;

interface Pool extends Connection
{
    /**
     * @return int Current number of connections in the pool.
     */
    public function getConnectionCount();

    /**
     * @return int Current number of idle connections in the pool.
     */
    public function getIdleConnectionCount();

    /**
     * @return int Maximum number of connections.
     */
    public function getMaxConnections();
}
