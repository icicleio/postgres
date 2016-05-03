<?php
namespace Icicle\Postgres;

use function Icicle\Postgres\connect;

class ConnectionPool extends AbstractPool
{
    const DEFAULT_MAX_CONNECTIONS = 100;

    /**
     * @var string
     */
    private $connectionString;

    /**
     * @var float
     */
    private $connectTimeout;

    /**
     * @var int
     */
    private $maxConnections;

    /**
     * @param string $connectionString
     * @param float|int $connectTimeout
     * @param int $maxConnections
     */
    public function __construct($connectionString, $connectTimeout = 0, $maxConnections = self::DEFAULT_MAX_CONNECTIONS)
    {
        parent::__construct();

        $this->connectionString = (string) $connectionString;
        $this->connectTimeout = (float) $connectTimeout;

        $this->maxConnections = (int) $maxConnections;
        if (1 > $this->maxConnections) {
            $this->maxConnections = 1;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function createConnection()
    {
        return connect($this->connectionString, $this->connectTimeout);
    }

    /**
     * {@inheritdoc}
     */
    public function getMaxConnections()
    {
        return $this->maxConnections;
    }
}
