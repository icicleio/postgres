<?php
namespace Icicle\Tests\Postgres;

use Icicle\Coroutine;
use Icicle\Loop;
use Icicle\Postgres\{CommandResult, Connection, Statement, Transaction, TupleResult};

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
            [4, 'execute', TupleResult::class, "SELECT * FROM test WHERE id=\$1 AND time>\$2", 1, time()],
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
                return yield $result;
            }));

        $pool = $this->createPool($connections);

        $coroutine = Coroutine\create(function () use ($method, $pool, $params, $result) {
            $return = yield $pool->{$method}(...$params);

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
        $rounds = 3;
        $result = $this->getMockBuilder($resultClass)
            ->disableOriginalConstructor()
            ->getMock();

        $connections = $this->makeConnectionSet($count);

        foreach ($connections as $connection) {
            $connection->method($method)
                ->with(...$params)
                ->will($this->returnCallback(function () use ($result) {
                    return yield $result;
                }));
        }

        $pool = $this->createPool($connections);

        $callback = function () use ($method, $pool, $params) {
            return yield from $pool->{$method}(...$params);
        };

        for ($i = 0; $i < $count * $rounds; ++$i) {
            $coroutine = Coroutine\create($callback);
            $coroutine->done();
        }

        Loop\run();
    }

    /**
     * @return array
     */
    public function getConnectionCounts()
    {
        return array_map(function ($count) { return [$count]; }, range(1, 10));
    }

    /**
     * @dataProvider getConnectionCounts
     *
     * @param int $count
     */
    public function testTransaction($count)
    {
        $connections = $this->makeConnectionSet($count);

        $connection = $connections[0];
        $result = $this->getMockBuilder(Transaction::class)
            ->disableOriginalConstructor()
            ->getMock();

        $connection->expects($this->once())
            ->method('transaction')
            ->with(Transaction::COMMITTED)
            ->will($this->returnCallback(function () use ($result) {
                return yield $result;
            }));

        $pool = $this->createPool($connections);

        $coroutine = Coroutine\create(function () use ($pool, $result) {
            $return = yield from $pool->transaction(Transaction::COMMITTED);
            $this->assertInstanceOf(Transaction::class, $return);
            yield from $return->rollback();
        });

        $coroutine->wait();
    }

    /**
     * @dataProvider getConnectionCounts
     *
     * @param int $count
     */
    public function testConsecutiveTransactions($count)
    {
        $rounds = 3;
        $result = $this->getMockBuilder(Transaction::class)
            ->disableOriginalConstructor()
            ->getMock();

        $connections = $this->makeConnectionSet($count);

        foreach ($connections as $connection) {
            $connection->method('transaction')
                ->with(Transaction::COMMITTED)
                ->will($this->returnCallback(function () use ($result) {
                    return yield $result;
                }));
        }

        $pool = $this->createPool($connections);

        $callback = function () use ($pool, $result) {
            $transaction = yield from $pool->transaction(Transaction::COMMITTED);
            yield from $transaction->rollback();
        };

        for ($i = 0; $i < $count * $rounds; ++$i) {
            $coroutine = Coroutine\create($callback);
            $coroutine->done();
        }

        Loop\run();
    }
}
