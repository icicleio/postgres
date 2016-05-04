<?php
namespace Icicle\Postgres;

use Icicle\Exception\InvalidArgumentError;
use Icicle\Observable\Emitter;
use Icicle\Postgres\Exception\FailureException;

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
            $count = \pg_num_rows($this->handle);

            for ($i = 0; $i < $count; ++$i) {
                $result = \pg_fetch_assoc($this->handle);

                if (false === $result) {
                    throw new FailureException(\pg_result_error($this->handle));
                }

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
     *
     * @throws \Icicle\Exception\InvalidArgumentError If the field number does not exist in the result.
     */
    public function fieldName($fieldNum)
    {
        return \pg_field_name($this->handle, $this->filterNameOrNum($fieldNum));
    }

    /**
     * @param string $fieldName
     *
     * @return int Index of field with given name.
     *
     * @throws \Icicle\Exception\InvalidArgumentError If the field name does not exist in the result.
     */
    public function fieldNum($fieldName)
    {
        $result = \pg_field_num($this->handle, $fieldName);

        if (-1 === $result) {
            throw new InvalidArgumentError(\sprintf('No field with name "%s" in result', $fieldName));
        }

        return $result;
    }

    /**
     * @param int|string $fieldNameOrNum Field name or index.
     *
     * @return string Name of the field type.
     *
     * @throws \Icicle\Exception\InvalidArgumentError If the field number does not exist in the result.
     */
    public function fieldType($fieldNameOrNum)
    {
        return \pg_field_type($this->handle, $this->filterNameOrNum($fieldNameOrNum));
    }

    /**
     * @param int|string $fieldNameOrNum Field name or index.
     *
     * @return int Storage required for field. -1 indicates a variable length field.
     *
     * @throws \Icicle\Exception\InvalidArgumentError If the field number does not exist in the result.
     */
    public function fieldSize($fieldNameOrNum)
    {
        return \pg_field_size($this->handle, $this->filterNameOrNum($fieldNameOrNum));
    }

    /**
     * @return int Number of rows in the result set.
     */
    public function count()
    {
        return $this->numRows();
    }

    /**
     * @param int|string $fieldNameOrNum Field name or index.
     *
     * @return int Field index.
     *
     * @throws \Icicle\Exception\InvalidArgumentError
     */
    private function filterNameOrNum($fieldNameOrNum)
    {
        if (is_string($fieldNameOrNum)) {
            return $this->fieldNum($fieldNameOrNum);
        }

        if (!is_int($fieldNameOrNum)) {
            throw new InvalidArgumentError('Must provide a string name or integer field number');
        }

        if (0 > $fieldNameOrNum || $this->numFields() <= $fieldNameOrNum) {
            throw new InvalidArgumentError(\sprintf('No field with index %d in result', $fieldNameOrNum));
        }

        return $fieldNameOrNum;
    }
}