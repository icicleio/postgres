<?php
namespace Icicle\Postgres;

use Icicle\Observable\Emitter;

class TupleResult extends Emitter implements \Countable
{
    /**
     * @var resource PostgreSQL result resource.
     */
    private $handle;

    /**
     * @param resource $handle PostgreSQL result resource.
     */
    public function __construct($handle)
    {
        $this->handle = $handle;

        parent::__construct(function (callable $emit) {
            for ($i = 0; $result = \pg_fetch_assoc($this->handle); ++$i) {
                yield $emit($result);
            }

            yield $i;
        });
    }

    /**
     * Frees the result resource.
     */
    public function __destruct()
    {
        \pg_free_result($this->handle);
    }

    /**
     * @return int Number of rows in the result set.
     */
    public function numRows()
    {
        return \pg_num_rows($this->handle);
    }

    /**
     * @return int Number of fields in each row.
     */
    public function numFields()
    {
        return \pg_num_fields($this->handle);
    }

    /**
     * @param int $fieldNum
     *
     * @return string Column name at index $fieldNum
     */
    public function fieldName($fieldNum)
    {
        return (string) \pg_field_name($this->handle, $fieldNum);
    }

    /**
     * @param string $fieldName
     *
     * @return int Index of field with given name.
     */
    public function fieldNum($fieldName)
    {
        $result = \pg_field_num($this->handle, $fieldName);

        return false === $result ? -1 : $result;
    }

    /**
     * @return int Number of rows in the result set.
     */
    public function count()
    {
        return $this->numRows();
    }
}