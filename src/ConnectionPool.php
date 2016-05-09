<?php
namespace Icicle\Postgres;

use function Icicle\Postgres\connect;

class ConnectionPool extends AbstractPool
{
    const DEFAULT_MAX_CONNECTIONS = 100;
    const DEFAULT_CONNECT_TIMEOUT = 5;

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
     * @param int $maxConnections
     * @param float|int $connectTimeout
     */
    public function __construct(
        $connectionString,
        $maxConnections = self::DEFAULT_MAX_CONNECTIONS,
        $connectTimeout = self::DEFAULT_CONNECT_TIMEOUT
    ) {
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
