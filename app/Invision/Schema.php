<?php

namespace App\Invision;

/**
 * Schema metadata container class
 */
class Schema
{
    /**
     * Table name
     * @var string
     */
    protected $_tableName;

    /**
     * Table columns
     * @var array
     */
    protected $_columns = [];

    /**
     * Schema constructor.
     * @param \stdClass $schema JSON stdClass object for a specific table
     */
    public function __construct( \stdClass $schema )
    {
        $this->_tableName = $schema->name;

        foreach ( $schema->columns as $column )
        {
            $this->parseColumn( $column );
        }
    }

    /**
     * @return array
     */
    public function getColumns()
    {
        return $this->_columns;
    }

    /**
     * @param \stdClass $column
     */
    protected function parseColumn(\stdClass $column)
    {
        // Parse the column type
        $type = 'string';
        if ( preg_match( '/^(?:(?:VAR)?CHAR)|(?:(?:[a-zA-Z]+)?TEXT)$/i', $column->type ) )
        {
            $type = 'string';
        }
        elseif ( preg_match( '/^(?:(?:[a-zA-Z]+)?INT)$/i', $column->type ) )
        {
            $type = 'int';
        }

        $this->_columns[] = [
            'name'      => (string)$column->name,
            'type'      => $type,
            'nullable'  => (bool)$column->allow_null,
            'comment'   => (string)$column->comment
        ];
    }
}