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
        string $connectionString,
        int $maxConnections = self::DEFAULT_MAX_CONNECTIONS,
        float $connectTimeout = self::DEFAULT_CONNECT_TIMEOUT
    ) {
        parent::__construct();

        $this->connectionString = $connectionString;
        $this->connectTimeout = $connectTimeout;

        $this->maxConnections = $maxConnections;
        if (1 > $this->maxConnections) {
            $this->maxConnections = 1;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function createConnection(): \Generator
    {
        return connect($this->connectionString, $this->connectTimeout);
    }

    /**
     * {@inheritdoc}
     */
    public function getMaxConnections(): int
    {
        return $this->maxConnections;
    }
}
