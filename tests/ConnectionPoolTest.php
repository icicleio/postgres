<?php
namespace Icicle\Tests\Postgres;

use Icicle\Postgres\ConnectionPool;

class ConnectionPoolTest extends AbstractPoolTest
{
    /**
     * @param array $connections
     *
     * @return \Icicle\Postgres\Pool
     */
    protected function createPool(array $connections)
    {
        $mock = $this->getMockBuilder(ConnectionPool::class)
            ->setConstructorArgs(['connection string', count($connections)])
            ->setMethods(['createConnection'])
            ->getMock();

        $mock->method('createConnection')
            ->will($this->returnCallback(function () use ($connections): \Generator {
                static $count = 0;
                return yield $connections[$count++];
            }));

        return $mock;
    }
}
