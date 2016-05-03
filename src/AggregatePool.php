<?php
namespace Icicle\Postgres;

use Icicle\Postgres\Exception\PoolError;

class AggregatePool extends AbstractPool
{
    /**
     * @param \Icicle\Postgres\Connection $connection
     */
    public function addConnection(Connection $connection)
    {
        parent::addConnection($connection);
    }

    /**
     * {@inheritdoc}
     */
    protected function createConnection()
    {
        throw new PoolError('Creating connections is not available in an aggregate pool');
    }

    /**
     * {@inheritdoc}
     */
    public function getMaxConnections()
    {
        $count = $this->getConnectionCount();

        if (!$count) {
            throw new PoolError('No connections in aggregate pool');
        }

        return $count;
    }
}
