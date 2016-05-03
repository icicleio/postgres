<?php
namespace Icicle\Tests\Postgres;

use Icicle\Postgres\AggregatePool;

class AggregatePoolTest extends AbstractPoolTest
{
    /**
     * @param array $connections
     *
     * @return \Icicle\Postgres\Pool
     */
    protected function createPool(array $connections)
    {
        $mock = $this->getMockBuilder(AggregatePool::class)
            ->setConstructorArgs(['', 0, count($connections)])
            ->setMethods(['createConnection'])
            ->getMock();

        $mock->method('createConnection')
            ->will($this->returnCallback(function () {
                $this->fail('The createConnection() method should not be called.');
            }));

        foreach ($connections as $connection) {
            $mock->addConnection($connection);
        }

        return $mock;
    }
}
