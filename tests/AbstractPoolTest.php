<?php
namespace Icicle\Tests\Postgres;

use Icicle\Coroutine;
use Icicle\Loop;
use Icicle\Postgres\CommandResult;
use Icicle\Postgres\Connection;
use Icicle\Postgres\Statement;
use Icicle\Postgres\TupleResult;

abstract class AbstractPoolTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @param array $connections
     *
     * @return \Icicle\Postgres\Pool
     */
    abstract protected function createPool(array $connections);

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|\Icicle\Postgres\Connection
     */
    private function createConnection()
    {
        return $this->getMock(Connection::class);
    }

    /**
     * @param int $count
     *
     * @return \Icicle\Postgres\Connection[]|\PHPUnit_Framework_MockObject_MockObject[]
     */
    private function makeConnectionSet($count)
    {
        $connections = [];

        for ($i = 0; $i < $count; ++$i) {
            $connections[] = $this->createConnection();
        }

        return $connections;
    }

    /**
     * @return array
     */
    public function getMethodsAndResults()
    {
        return [
            [3, 'query', TupleResult::class, "SELECT * FROM test"],
            [2, 'query', CommandResult::class, "INSERT INTO test VALUES (1, 7)"],
            [1, 'prepare', Statement::class, "SELECT * FROM test WHERE id=\$1"],
            [3, 'execute', TupleResult::class, "SELECT * FROM test WHERE id=\$1 AND time>\$2", 1, time()],
        ];
    }

    /**
     * @dataProvider getMethodsAndResults
     *
     * @param int $count
     * @param string $method
     * @param string $resultClass
     * @param mixed ...$params
     */
    public function testSingleQuery($count, $method, $resultClass, ...$params)
    {
        $result = $this->getMockBuilder($resultClass)
            ->disableOriginalConstructor()
            ->getMock();

        $connections = $this->makeConnectionSet($count);

        $connection = $connections[0];
        $connection->expects($this->once())
            ->method($method)
            ->with(...$params)
            ->will($this->returnCallback(function () use ($result) {
                yield $result;
            }));

        $pool = $this->createPool($connections);

        $coroutine = Coroutine\create(function () use ($method, $pool, $params, $result) {
            $return = (yield $pool->{$method}(...$params));

            $this->assertSame($result, $return);
        });

        $coroutine->wait();
    }

    /**
     * @dataProvider getMethodsAndResults
     *
     * @param int $count
     * @param string $method
     * @param string $resultClass
     * @param mixed ...$params
     */
    public function testConsecutiveQueries($count, $method, $resultClass, ...$params)
    {
        $connections = $this->makeConnectionSet($count);

        foreach ($connections as $connection) {
            $connection->expects($this->exactly(2))
                ->method($method)
                ->with(...$params);
        }

        $pool = $this->createPool($connections);

        $callback = function () use ($method, $pool, $params) {
            yield $pool->{$method}(...$params);
        };

        for ($i = 0; $i < $count * 2; ++$i) {
            $coroutine = Coroutine\create($callback);
            $coroutine->done();
        }

        Loop\run();
    }
}
