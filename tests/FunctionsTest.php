<?php
namespace Icicle\Tests\Postgres;

use Icicle\Coroutine;
use Icicle\Postgres;
use Icicle\Postgres\Connection;

class FunctionsTest extends \PHPUnit_Framework_TestCase
{
    public function testConnect()
    {
        $coroutine = Coroutine\create(function () {
            $connection = yield from Postgres\connect('host=localhost user=postgres', 1);

            $this->assertInstanceOf(Connection::class, $connection);
        });

        $coroutine->wait();
    }

    /**
     * @expectedException \Icicle\Postgres\Exception\FailureException
     */
    public function testConnectInvalidUser()
    {
        $coroutine = Coroutine\create(function () {
            $connection = yield from Postgres\connect('host=localhost user=invalid', 1);
        });

        $coroutine->wait();
    }

    /**
     * @expectedException \Icicle\Postgres\Exception\FailureException
     */
    public function testConnectInvalidConnectionString()
    {
        $coroutine = Coroutine\create(function () {
            $connection = yield from Postgres\connect('invalid connection string', 1);
        });

        $coroutine->wait();
    }

    /**
     * @expectedException \Icicle\Postgres\Exception\FailureException
     */
    public function testConnectInvalidHost()
    {
        $coroutine = Coroutine\create(function () {
            $connection = yield from Postgres\connect('hostaddr=invalid.host user=postgres', 1);
        });

        $coroutine->wait();
    }
}
