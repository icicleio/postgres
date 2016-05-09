<?php
namespace Icicle\Tests\Postgres;

use Icicle\Coroutine;
use Icicle\Exception\InvalidArgumentError;
use Icicle\Loop;
use Icicle\Postgres\{BasicConnection, CommandResult, Transaction, TupleResult};
use Icicle\Postgres\Exception\{QueryError, TransactionError};

class BasicConnectionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Icicle\Postgres\Connection
     */
    protected $connection;

    /**
     * @var resource PostgreSQL connection resource.
     */
    protected $handle;

    /**
     * @return array Start test data for database.
     */
    public function getData()
    {
        return [
            ['icicle', 'io'],
            ['github', 'com'],
            ['google', 'com'],
            ['php', 'net'],
        ];
    }

    public function setUp()
    {
        $this->handle = \pg_connect('host=localhost user=postgres');
        $socket = \pg_socket($this->handle);

        $result = \pg_query($this->handle, "CREATE TABLE test (domain VARCHAR(63), tld VARCHAR(63), PRIMARY KEY (domain, tld))");

        if (!$result) {
            $this->fail('Could not create test table.');
        }

        foreach ($this->getData() as $row) {
            $result = \pg_query_params($this->handle, "INSERT INTO test VALUES (\$1, \$2)", $row);

            if (!$result) {
                $this->fail('Could not insert test data.');
            }
        }

        $this->connection = new BasicConnection($this->handle, $socket);
    }

    public function tearDown()
    {
        \pg_query($this->handle, "ROLLBACK");
        \pg_query($this->handle, "DROP TABLE test");
    }

    public function testQueryWithTupleResult()
    {
        $coroutine = Coroutine\create(function () {
            /** @var \Icicle\Postgres\TupleResult $result */
            $result = yield from $this->connection->query("SELECT * FROM test");

            $this->assertInstanceOf(TupleResult::class, $result);

            $this->assertSame(4, $result->numRows());
            $this->assertSame(2, $result->numFields());
            $this->assertSame(4, count($result));

            $this->assertSame('domain', $result->fieldName(0));
            $this->assertSame('tld', $result->fieldName(1));

            $this->assertSame(0, $result->fieldNum('domain'));
            $this->assertSame(1, $result->fieldNum('tld'));

            $this->assertSame(-1, $result->fieldSize('domain'));
            $this->assertSame('varchar', $result->fieldType('tld'));

            try {
                $result->fieldNum('test');
                $this->fail('Getting number of unknown field should fail.');
            } catch (InvalidArgumentError $exception) {}

            try {
                $result->fieldName(-1);
                $this->fail('Getting name of unknown field should fail.');
            } catch (InvalidArgumentError $exception) {}

            try {
                $result->fieldType(-1);
                $this->fail('Getting type of unknown field should fail.');
            } catch (InvalidArgumentError $exception) {}

            try {
                $result->fieldSize(-1);
                $this->fail('Getting size of unknown field should fail.');
            } catch (InvalidArgumentError $exception) {}

            $iterator = $result->getIterator();

            $data = $this->getData();

            for ($i = 0; yield from $iterator->isValid(); ++$i) {
                $row = $iterator->getCurrent();
                $this->assertSame($data[$i][0], $row['domain']);
                $this->assertSame($data[$i][1], $row['tld']);
            }
        });

        $coroutine->wait();
    }

    public function testQueryWithCommandResult()
    {
        $coroutine = Coroutine\create(function () {
            /** @var \Icicle\Postgres\CommandResult $result */
            $result = yield from $this->connection->query("INSERT INTO test VALUES ('canon', 'jp')");

            $this->assertInstanceOf(CommandResult::class, $result);
            $this->assertSame(1, $result->affectedRows());
        });

        $coroutine->wait();
    }

    /**
     * @expectedException \Icicle\Postgres\Exception\QueryError
     */
    public function testQueryWithEmptyQuery()
    {
        $coroutine = Coroutine\create(function () {
            /** @var \Icicle\Postgres\CommandResult $result */
            $result = yield from $this->connection->query('');
        });

        $coroutine->wait();
    }

    /**
     * @expectedException \Icicle\Postgres\Exception\QueryError
     */
    public function testQueryWithSyntaxError()
    {
        $coroutine = Coroutine\create(function () {
            /** @var \Icicle\Postgres\CommandResult $result */
            $result = yield from $this->connection->query("SELECT & FROM test");
        });

        $coroutine->wait();
    }

    public function testPrepare()
    {
        $coroutine = Coroutine\create(function () {
            $query = "SELECT * FROM test WHERE domain=\$1";

            /** @var \Icicle\Postgres\Statement $statement */
            $statement = yield from $this->connection->prepare($query);

            $this->assertSame($query, $statement->getQuery());

            $data = $this->getData()[0];

            /** @var \Icicle\Postgres\TupleResult $result */
            $result = yield from $statement->execute($data[0]);

            $this->assertInstanceOf(TupleResult::class, $result);

            $this->assertSame(1, $result->numRows());
            $this->assertSame(2, $result->numFields());

            $this->assertSame('domain', $result->fieldName(0));
            $this->assertSame('tld', $result->fieldName(1));

            $this->assertSame(0, $result->fieldNum('domain'));
            $this->assertSame(1, $result->fieldNum('tld'));

            $iterator = $result->getIterator();

            while (yield from $iterator->isValid()) {
                $row = $iterator->getCurrent();
                $this->assertSame($data[0], $row['domain']);
                $this->assertSame($data[1], $row['tld']);
            }
        });

        $coroutine->wait();
    }

    public function testExecute()
    {
        $coroutine = Coroutine\create(function () {
            $data = $this->getData()[0];

            /** @var \Icicle\Postgres\TupleResult $result */
            $result = yield from $this->connection->execute("SELECT * FROM test WHERE domain=\$1", $data[0]);

            $this->assertInstanceOf(TupleResult::class, $result);

            $this->assertSame(1, $result->numRows());
            $this->assertSame(2, $result->numFields());

            $this->assertSame('domain', $result->fieldName(0));
            $this->assertSame('tld', $result->fieldName(1));

            $this->assertSame(0, $result->fieldNum('domain'));
            $this->assertSame(1, $result->fieldNum('tld'));

            $iterator = $result->getIterator();

            while (yield from $iterator->isValid()) {
                $row = $iterator->getCurrent();
                $this->assertSame($data[0], $row['domain']);
                $this->assertSame($data[1], $row['tld']);
            }
        });

        $coroutine->wait();
    }

    /**
     * @depends testQueryWithTupleResult
     */
    public function testSimultaneousQuery()
    {
        $callback = function () {
            /** @var \Icicle\Postgres\TupleResult $result */
            $result = yield from $this->connection->query("SELECT * FROM test");

            $iterator = $result->getIterator();

            $data = $this->getData();

            for ($i = 0; yield from $iterator->isValid(); ++$i) {
                $row = $iterator->getCurrent();
                $this->assertSame($data[$i][0], $row['domain']);
                $this->assertSame($data[$i][1], $row['tld']);
            }
        };

        $coroutine = Coroutine\create($callback);
        $coroutine->done();

        $coroutine = Coroutine\create($callback);
        $coroutine->done();

        Loop\run();
    }

    /**
     * @depends testSimultaneousQuery
     */
    public function testSimultaneousQueryWithFirstFailing()
    {
        $callback = function ($query) {
            /** @var \Icicle\Postgres\TupleResult $result */
            $result = yield from $this->connection->query($query);

            $iterator = $result->getIterator();

            $data = $this->getData();

            for ($i = 0; yield from $iterator->isValid(); ++$i) {
                $row = $iterator->getCurrent();
                $this->assertSame($data[$i][0], $row['domain']);
                $this->assertSame($data[$i][1], $row['tld']);
            }
        };

        $coroutine = Coroutine\create($callback, "SELECT & FROM test");
        $coroutine->done(null, function ($exception) {
            $this->assertInstanceOf(QueryError::class, $exception);
        });

        $coroutine = Coroutine\create($callback, "SELECT * FROM test");
        $coroutine->done();

        Loop\run();
    }

    public function testSimultaneousQueryAndPrepare()
    {
        $coroutine = Coroutine\create(function () {
            /** @var \Icicle\Postgres\TupleResult $result */
            $result = yield from $this->connection->query("SELECT * FROM test");

            $iterator = $result->getIterator();

            $data = $this->getData();

            for ($i = 0; yield from $iterator->isValid(); ++$i) {
                $row = $iterator->getCurrent();
                $this->assertSame($data[$i][0], $row['domain']);
                $this->assertSame($data[$i][1], $row['tld']);
            }
        });
        $coroutine->done();

        $coroutine = Coroutine\create(function () {
            /** @var \Icicle\Postgres\Statement $statement */
            $statement = (yield $this->connection->prepare("SELECT * FROM test"));

            /** @var \Icicle\Postgres\TupleResult $result */
            $result = yield from $statement->execute();

            $iterator = $result->getIterator();

            $data = $this->getData();

            for ($i = 0; yield from $iterator->isValid(); ++$i) {
                $row = $iterator->getCurrent();
                $this->assertSame($data[$i][0], $row['domain']);
                $this->assertSame($data[$i][1], $row['tld']);
            }
        });
        $coroutine->done();

        Loop\run();
    }

    public function testSimultaneousPrepareAndExecute()
    {
        $coroutine = Coroutine\create(function () {
            /** @var \Icicle\Postgres\Statement $statement */
            $statement = yield from $this->connection->prepare("SELECT * FROM test");

            /** @var \Icicle\Postgres\TupleResult $result */
            $result = yield from $statement->execute();

            $iterator = $result->getIterator();

            $data = $this->getData();

            for ($i = 0; yield from $iterator->isValid(); ++$i) {
                $row = $iterator->getCurrent();
                $this->assertSame($data[$i][0], $row['domain']);
                $this->assertSame($data[$i][1], $row['tld']);
            }
        });
        $coroutine->done();

        $coroutine = Coroutine\create(function () {
            /** @var \Icicle\Postgres\TupleResult $result */
            $result = yield from $this->connection->execute("SELECT * FROM test");

            $iterator = $result->getIterator();

            $data = $this->getData();

            for ($i = 0; yield from $iterator->isValid(); ++$i) {
                $row = $iterator->getCurrent();
                $this->assertSame($data[$i][0], $row['domain']);
                $this->assertSame($data[$i][1], $row['tld']);
            }
        });
        $coroutine->done();

        Loop\run();
    }

    public function testTransaction()
    {
        $coroutine = Coroutine\create(function () {
            $isolation = Transaction::COMMITTED;

            /** @var \Icicle\Postgres\Transaction $transaction */
            $transaction = yield from $this->connection->transaction($isolation);

            $this->assertInstanceOf(Transaction::class, $transaction);

            $data = $this->getData()[0];

            $this->assertTrue($transaction->isActive());
            $this->assertSame($isolation, $transaction->getIsolationLevel());

            yield $transaction->savepoint('test');

            $result = yield from $transaction->execute("SELECT * FROM test WHERE domain=\$1 FOR UPDATE", $data[0]);

            yield $transaction->rollbackTo('test');

            yield $transaction->commit();

            $this->assertFalse($transaction->isActive());

            try {
                $result = yield from $transaction->execute("SELECT * FROM test");
                $this->fail('Query should fail after transaction commit');
            } catch (TransactionError $exception) {
                // Exception expected.
            }
        });

        $coroutine->wait();
    }
}
