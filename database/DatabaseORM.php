<?php

namespace App\Database;

class DatabaseORM
{
    /**
     * @var \PDO
     */
    public static $_db;

    /**
     * @var \PDOStatement[]
     */
    protected static $_stmt = [];

    /**
     * @var string
     */
    protected static $_identifier_quote_character;

    /**
     * @var array
     */
    private static $_tableColumns = [];

    /**
     * Todos os dados são armazenados aqui.
     *
     * @var \stdClass
     */
    protected $data;

    /**
     * Se o valor de um campo for alterado ele é armazenado aqui.
     *
     * @var \stdClass
     */
    protected $dirty;

    /**
     * @var string
     */
    protected static $_primary_column_name = 'id';

    /**
     * @var string
     */
    protected static $_tableName = 'table_name';

    /**
     * Construtor da classe.
     *
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        static::getFieldnames();

        $this->clearDirtyFields();

        if (is_array($data)) {
            $this->hydrate($data);
        }
    }

    /**
     * Verifica se o objeto tem dados anexados.
     *
     * @return bool
     */
    public function hasData()
    {
        return is_object($this->data);
    }


    /**
     * Returns true if data present else throws an Exception
     *
     * @throws \Exception
     * @return bool
     */
    public function dataPresent()
    {
        if (! $this->hasData()) {
            throw new \Exception('No data');
        }

        return true;
    }

    /**
     * Set field in data object if doesnt match a native object member
     * Initialise the data store if not an object
     *
     * @param string $name
     * @param mixed  $value
     *
     * @return void
     */
    public function __set($name, $value)
    {
        if (! $this->hasData()) {
            $this->data = new \stdClass();
        }
        $this->data->$name = $value;
        $this->markFieldDirty($name);
    }

    /**
     * Mark the field as dirty, so it will be set in inserts and updates
     *
     * @param string $name
     */
    public function markFieldDirty($name)
    {
        $this->dirty->$name = true; // field became dirty
    }

    /**
     * Return true if filed is dirty else false
     *
     * @param string $name
     * @return bool
     */
    public function isFieldDirty($name)
    {
        return isset($this->dirty->$name) && ($this->dirty->$name === true);
    }

    /**
     * resets what fields have been considered dirty ie. been changed without being saved to the db
     */
    public function clearDirtyFields()
    {
        $this->dirty = new \stdClass();
    }

    /**
     * Try and get the object member from the data object
     * if it doesnt match a native object member
     *
     * @param string $name
     *
     * @throws \Exception
     * @return mixed
     */
    public function __get($name)
    {
        if (! $this->hasData()) {
            throw new \Exception("data property=$name has not been initialised", 1);
        }

        if (property_exists($this->data, $name)) {
            return $this->data->$name;
        }

        $trace = debug_backtrace();
        throw new \Exception(
            'Undefined property via __get(): ' . $name .
            ' in ' . $trace[0]['file'] .
            ' on line ' . $trace[0]['line'],
            1
        );
    }

    /**
     * Test the existence of the object member from the data object
     * if it doesnt match a native object member
     *
     * @param string $name
     *
     * @return bool
     */
    public function __isset($name)
    {
        if ($this->hasData() && property_exists($this->data, $name)) {
            return true;
        }

        return false;
    }

    /**
     * set the db connection for this and all sub-classes to use
     * if a sub class overrides $_db it can have it's own db connection if required
     * params are as new PDO(...)
     * set PDO to throw exceptions on error
     *
     * @param string $dsn
     * @param string $username
     * @param string $password
     * @param array  $driverOptions
     */
    public static function connectDb($dsn, $username = '', $password = '', $driverOptions = [])
    {
        static::$_db = new \PDO($dsn);
        // static::$_db = new \PDO($dsn, $username, $password, $driverOptions);
        static::$_db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION); // Set Errorhandling to Exception
        static::_setup_identifier_quote_character();
    }

    /**
     * Detect and initialise the character used to quote identifiers
     * (table names, column names etc).
     *
     * @return void
     */
    public static function _setup_identifier_quote_character()
    {
        if (is_null(static::$_identifier_quote_character)) {
            static::$_identifier_quote_character = static::_detect_identifier_quote_character();
        }
    }

    /**
     * Return the correct character used to quote identifiers (table
     * names, column names etc) by looking at the driver being used by PDO.
     *
     * @return string
     */
    protected static function _detect_identifier_quote_character()
    {
        switch (static::getDriverName()) {
            case 'pgsql':
            case 'sqlsrv':
            case 'dblib':
            case 'mssql':
            case 'sybase':
                return '"';
            case 'mysql':
            case 'sqlite':
            case 'sqlite2':
            default:
                return '`';
        }
    }

    /**
     * return the driver name for the current database connection
     * @throws \Exception
     * @return string
     */
    protected static function getDriverName()
    {
        if (! static::$_db) {
            throw new \Exception('No database connection setup');
        }
        return static::$_db->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }

    /**
     * Quote a string that is used as an identifier
     * (table names, column names etc). This method can
     * also deal with dot-separated identifiers eg table.column
     *
     * @param string $identifier
     * @return string
     */
    protected static function _quote_identifier($identifier)
    {
        $class = get_called_class();
        $parts = explode('.', $identifier);
        $parts = array_map([
            $class,
            '_quote_identifier_part'
        ], $parts);
        return join('.', $parts);
    }


    /**
     * This method performs the actual quoting of a single
     * part of an identifier, using the identifier quote
     * character specified in the config (or autodetected).
     *
     * @param string $part
     * @return string
     */
    protected static function _quote_identifier_part($part)
    {
        if ($part === '*') {
            return $part;
        }
        return static::$_identifier_quote_character . $part . static::$_identifier_quote_character;
    }

    /**
     * Get and cache on first call the column names assocaited with the current table
     *
     * @return array of column names for the current table
     */
    protected static function getFieldnames()
    {
        $class = get_called_class();

        if (! isset(self::$_tableColumns[$class])) {
            $st = static::execute('PRAGMA table_info(' . static::_quote_identifier(static::$_tableName) . ')');

            self::$_tableColumns[$class] = [];
            $columns = $st->fetchAll();

            foreach ($columns as $column) {
                self::$_tableColumns[$class][] = $column['name'];
            }
        }

        return self::$_tableColumns[$class];
    }

    /**
     * Given an associative array of key value pairs
     * set the corresponding member value if associated with a table column
     * ignore keys which dont match a table column name
     *
     * @return void
     */
    public function hydrate($data)
    {
        foreach (static::getFieldnames() as $fieldname) {
            if (isset($data[$fieldname])) {
                $this->$fieldname = $data[$fieldname];
            } elseif (! isset($this->$fieldname)) { // PDO pre populates fields before calling the constructor, so dont null unless not set
                $this->$fieldname = null;
            }
        }
    }

    /**
     * set all members to null that are associated with table columns
     *
     * @return void
     */
    public function clear()
    {
        foreach (static::getFieldnames() as $fieldname) {
            $this->$fieldname = null;
        }
        $this->clearDirtyFields();
    }

    /**
     * @return array
     */
    public function __sleep()
    {
        return static::getFieldnames();
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $a = [];
        foreach (static::getFieldnames() as $fieldname) {
            $a[$fieldname] = $this->$fieldname;
        }
        return $a;
    }

    /**
     * Get the record with the matching primary key
     *
     * @param string $id
     * @return Object
     */
    public static function getById($id)
    {
        return static::fetchOneWhere(static::_quote_identifier(static::$_primary_column_name) . ' = ?', [$id]);
    }

    /**
     * Get the first record in the table
     *
     * @return Object
     */
    public static function first()
    {
        return static::fetchOneWhere('1=1 ORDER BY ' . static::_quote_identifier(static::$_primary_column_name) . ' ASC');
    }

    /**
     * Get the last record in the table
     *
     * @return Object
     */
    public static function last()
    {
        return static::fetchOneWhere('1=1 ORDER BY ' . static::_quote_identifier(static::$_primary_column_name) . ' DESC');
    }

    /**
     * Find records with the matching primary key
     *
     * @param string $id
     * @return object[] of objects for matching records
     */
    public static function find($id)
    {
        $find_by_method = 'find_by_' . (static::$_primary_column_name);
        static::$find_by_method($id);
    }

    /**
     * handles calls to non-existant static methods, used to implement dynamic finder and counters ie.
     *  find_by_name('tom')
     *  find_by_title('a great book')
     *  count_by_name('tom')
     *  count_by_title('a great book')
     *
     * @param string $name
     * @param string $arguments
     * @throws \Exception
     * @return mixed int|object[]|object
     */
    public static function __callStatic($name, $arguments)
    {
        // Note: value of $name is case sensitive.
        if (preg_match('/^find_by_/', $name) === 1) {
            // it's a find_by_{fieldname} dynamic method
            $fieldname = mb_substr($name, 8); // remove find by
            $match     = $arguments[0];
            return static::fetchAllWhereMatchingSingleField($fieldname, $match);
        } elseif (preg_match('/^findOne_by_/', $name) === 1) {
            // it's a findOne_by_{fieldname} dynamic method
            $fieldname = mb_substr($name, 11); // remove findOne_by_
            $match     = $arguments[0];
            return static::fetchOneWhereMatchingSingleField($fieldname, $match, 'ASC');
        } elseif (preg_match('/^first_by_/', $name) === 1) {
            // it's a first_by_{fieldname} dynamic method
            $fieldname = mb_substr($name, 9); // remove first_by_
            $match     = $arguments[0];
            return static::fetchOneWhereMatchingSingleField($fieldname, $match, 'ASC');
        } elseif (preg_match('/^last_by_/', $name) === 1) {
            // it's a last_by_{fieldname} dynamic method
            $fieldname = mb_substr($name, 8); // remove last_by_
            $match     = $arguments[0];
            return static::fetchOneWhereMatchingSingleField($fieldname, $match, 'DESC');
        } elseif (preg_match('/^count_by_/', $name) === 1) {
            // it's a count_by_{fieldname} dynamic method
            $fieldname = mb_substr($name, 9); // remove find by
            $match     = $arguments[0];
            if (is_array($match)) {
                return static::countAllWhere(static::_quote_identifier($fieldname) . ' IN (' . static::createInClausePlaceholders($match) . ')', $match);
            }
            return static::countAllWhere(static::_quote_identifier($fieldname) . ' = ?', [$match]);
        }
        throw new \Exception(__CLASS__ . ' not such static method[' . $name . ']');
    }

    /**
     * find one match based on a single field and match criteria
     *
     * @param string       $fieldname
     * @param string|array $match
     * @param string       $order ASC|DESC
     * @return object of calling class
     */
    public static function fetchOneWhereMatchingSingleField($fieldname, $match, $order)
    {
        if (is_array($match)) {
            return static::fetchOneWhere(static::_quote_identifier($fieldname) . ' IN (' . static::createInClausePlaceholders($match) . ') ORDER BY ' . static::_quote_identifier($fieldname) . ' ' . $order, $match);
        }
        return static::fetchOneWhere(static::_quote_identifier($fieldname) . ' = ? ORDER BY ' . static::_quote_identifier($fieldname) . ' ' . $order, [$match]);
    }


    /**
     * find multiple matches based on a single field and match criteria
     *
     * @param string       $fieldname
     * @param string|array $match
     * @return object[] of objects of calling class
     */
    public static function fetchAllWhereMatchingSingleField($fieldname, $match)
    {
        if (is_array($match)) {
            return static::fetchAllWhere(static::_quote_identifier($fieldname) . ' IN (' . static::createInClausePlaceholders($match) . ')', $match);
        }
        return static::fetchAllWhere(static::_quote_identifier($fieldname) . ' = ?', [$match]);
    }

    /**
     * for a given array of params to be passed to an IN clause return a string placeholder
     *
     * @param array $params
     * @return string
     */
    public static function createInClausePlaceholders($params)
    {
        return implode(',', array_fill(0, count($params), '?'));
    }

    /**
     * returns number of rows in the table
     *
     * @return int
     */
    public static function count()
    {
        $st = static::execute('SELECT COUNT(*) FROM ' . static::_quote_identifier(static::$_tableName));
        return (int) $st->fetchColumn(0);
    }

    /**
     * returns an integer count of matching rows
     *
     * @param string $SQLfragment conditions, grouping to apply (to right of WHERE keyword)
     * @param array  $params      optional params to be escaped and injected into the SQL query (standrd PDO syntax)
     * @return integer count of rows matching conditions
     */
    public static function countAllWhere($SQLfragment = '', $params = [])
    {
        $SQLfragment = self::addWherePrefix($SQLfragment);
        $st          = static::execute('SELECT COUNT(*) FROM ' . static::_quote_identifier(static::$_tableName) . $SQLfragment, $params);
        return (int) $st->fetchColumn(0);
    }

    /**
     * if $SQLfragment is not empty prefix with the WHERE keyword
     *
     * @param string $SQLfragment
     * @return string
     */
    protected static function addWherePrefix($SQLfragment)
    {
        return $SQLfragment ? ' WHERE ' . $SQLfragment : $SQLfragment;
    }


    /**
     * returns an array of objects of the sub-class which match the conditions
     *
     * @param string $SQLfragment conditions, sorting, grouping and limit to apply (to right of WHERE keywords)
     * @param array  $params      optional params to be escaped and injected into the SQL query (standrd PDO syntax)
     * @param bool   $limitOne    if true the first match will be returned
     * @return mixed object[]|object of objects of calling class
     */
    public static function fetchWhere($SQLfragment = '', $params = [], $limitOne = false)
    {
        $class = get_called_class();
        $SQLfragment = self::addWherePrefix($SQLfragment);
        $st = static::execute(
            'SELECT * FROM ' . static::_quote_identifier(static::$_tableName) . $SQLfragment . ($limitOne ? ' LIMIT 1' : ''),
            $params
        );
        $st->setFetchMode(\PDO::FETCH_ASSOC);

        if ($limitOne) {
            return new $class($st->fetch());
        }

        $results = [];

        foreach ($st->fetchAll() as $row) {
            $results[] = new $class($row);
        }

        return $results;
    }

    /**
     * returns an array of objects of the sub-class which match the conditions
     *
     * @param string $SQLfragment conditions, sorting, grouping and limit to apply (to right of WHERE keywords)
     * @param array  $params      optional params to be escaped and injected into the SQL query (standrd PDO syntax)
     * @return object[] of objects of calling class
     */
    public static function fetchAllWhere($SQLfragment = '', $params = [])
    {
        return static::fetchWhere($SQLfragment, $params, false);
    }

    /**
     * returns an object of the sub-class which matches the conditions
     *
     * @param string $SQLfragment conditions, sorting, grouping and limit to apply (to right of WHERE keywords)
     * @param array  $params      optional params to be escaped and injected into the SQL query (standrd PDO syntax)
     * @return object of calling class
     */
    public static function fetchOneWhere($SQLfragment = '', $params = [])
    {
        return static::fetchWhere($SQLfragment, $params, true);
    }

    /**
     * Delete a record by its primary key
     *
     * @return boolean indicating success
     */
    public static function deleteById($id)
    {
        $st = static::execute(
            'DELETE FROM ' . static::_quote_identifier(static::$_tableName) . ' WHERE ' . static::_quote_identifier(static::$_primary_column_name) . ' = ?',
            [$id]
        );
        return ($st->rowCount() === 1);
    }

    /**
     * Delete the current record
     *
     * @return boolean indicating success
     */
    public function delete()
    {
        return self::deleteById($this->{static::$_primary_column_name});
    }

    /**
     * Delete records based on an SQL conditions
     *
     * @param string $where  SQL fragment of conditions
     * @param array  $params optional params to be escaped and injected into the SQL query (standrd PDO syntax)
     * @return \PDOStatement
     */
    public static function deleteAllWhere($where, $params = [])
    {
        $st = static::execute(
            'DELETE FROM ' . static::_quote_identifier(static::$_tableName) . ' WHERE ' . $where,
            $params
        );
        return $st;
    }

    /**
     * do any validation in this function called before update and insert
     * should throw errors on validation failure.
     *
     * @return boolean true or throws exception on error
     */
    public static function validate()
    {
        return true;
    }

    /**
     * insert a row into the database table, and update the primary key field with the one generated on insert
     *
     * @param boolean $autoTimestamp      true by default will set updated_at & created_at fields if present
     * @param boolean $allowSetPrimaryKey if true include primary key field in insert (ie. you want to set it yourself)
     * @return boolean indicating success
     */
    public function insert($autoTimestamp = true, $allowSetPrimaryKey = false)
    {
        $pk = static::$_primary_column_name;
        $timeStr = gmdate('Y-m-d H:i:s');

        if ($autoTimestamp && in_array('created_at', static::getFieldnames(), true)) {
            $this->created_at = $timeStr;
        }

        if ($autoTimestamp && in_array('updated_at', static::getFieldnames(), true)) {
            $this->updated_at = $timeStr;
        }

        $this->validate();

        if ($allowSetPrimaryKey !== true) {
            $this->$pk = null;
        }

        $set = $this->insertString(! $allowSetPrimaryKey);
        $query = 'INSERT INTO ' . static::_quote_identifier(static::$_tableName) .
            ' ' . $set['columns'] . ' VALUES ' . $set['values'];
        $st = static::execute($query, $set['params']);

        if ($st->rowCount() === 1) {
            $this->{static::$_primary_column_name} = static::$_db->lastInsertId();
            $this->clearDirtyFields();
        }

        return ($st->rowCount() === 1);
    }

    /**
     * update the current record
     *
     * @param boolean $autoTimestamp true by default will set updated_at field if present
     * @return boolean indicating success
     */
    public function update($autoTimestamp = true)
    {
        if ($autoTimestamp && in_array('updated_at', static::getFieldnames(), true)) {
            $this->updated_at = gmdate('Y-m-d H:i:s');
        }

        $this->validate();

        $set = $this->setString();
        $query = 'UPDATE ' . static::_quote_identifier(static::$_tableName) .
            ' SET ' . $set['sql'] .
            ' WHERE ' . static::_quote_identifier(static::$_primary_column_name) .
            ' = ? ';
    
        $set['params'][] = $this->{static::$_primary_column_name};
        $st = static::execute($query, $set['params']);

        if ($st->rowCount() === 1) {
            $this->clearDirtyFields();
        }

        return ($st->rowCount() === 1);
    }

    /**
     * execute
     * convenience function for setting preparing and running a database query
     * which also uses the statement cache
     *
     * @param string $query  database statement with parameter place holders as PDO driver
     * @param array  $params array of parameters to replace the placeholders in the statement
     * @return \PDOStatement handle
     */
    public static function execute($query, $params = [])
    {
        $st = static::_prepare($query);
        $st->execute($params);

        return $st;
    }

    /**
     * prepare an SQL query via PDO
     *
     * @param string $query
     * @return \PDOStatement
     */
    protected static function _prepare($query)
    {
        if (! isset(static::$_stmt[$query])) {
            static::$_stmt[$query] = static::$_db->prepare($query);
        }

        return static::$_stmt[$query];
    }

    /**
     * call update if primary key field is present, else call insert
     *
     * @return boolean indicating success
     */
    public function save()
    {
        if ($this->{static::$_primary_column_name}) {
            return $this->update();
        }
        return $this->insert();
    }

    /**
     * @param boolean $ignorePrimary
     * @return string[string] ['sql' => string, 'params' => mixed[] ]
     */
    protected function insertString($ignorePrimary = true)
    {
        $columns = [];
        $values = [];
        $params = [];

        foreach (static::getFieldnames() as $field) {
            if ($ignorePrimary && $field === static::$_primary_column_name) {
                continue;
            }

            if (isset($this->$field) && $this->isFieldDirty($field)) { // Only if dirty
                $columns[] = static::_quote_identifier($field);

                if ($this->$field === null) {
                    // if empty set to NULL
                    $values[] = 'NULL';
                } else {
                    // Just set value normally as not empty string with NULL allowed
                    $values[] = ':' . $field;
                    $params[] = $this->$field;
                }
            }
        }

        $columns = '(' . implode(', ', $columns) . ')';
        $values = '(' . implode(', ', $values) . ')';

        return [
            'columns' => $columns,
            'values'  => $values,
            'params'  => $params,
        ];
    }

    /**
     * Create an SQL fragment to be used after the SET keyword in an SQL UPDATE
     * escaping parameters as necessary.
     * by default the primary key is not added to the SET string, but passing $ignorePrimary as false will add it
     *
     * @param boolean $ignorePrimary
     * @return string[string] ['sql' => string, 'params' => mixed[] ]
     */
    protected function setString($ignorePrimary = true)
    {
        // escapes and builds mysql SET string returning false, empty string or `field` = 'val'[, `field` = 'val']...
        /**
         * @var array $fragments individual SQL assignments
         */
        $fragments = [];

        /**
         * @var array $params values in order to insert into SQl assignment fragments
         */
        $params = [];

        foreach (static::getFieldnames() as $field) {
            if ($ignorePrimary && $field === static::$_primary_column_name) {
                continue;
            }

            if (isset($this->$field) && $this->isFieldDirty($field)) { // Only if dirty
                if ($this->$field === null) {
                    // if empty set to NULL
                    $fragments[] = static::_quote_identifier($field) . ' = NULL';
                } else {
                    // Just set value normally as not empty string with NULL allowed
                    $fragments[] = static::_quote_identifier($field) . ' = ?';
                    $params[]    = $this->$field;
                }
            }
        }

        $sqlFragment = implode(", ", $fragments);

        return [
            'sql'    => $sqlFragment,
            'params' => $params
        ];
    }

    /**
     * convert a date string or timestamp into a string suitable for assigning to a SQl datetime or timestamp field
     *
     * @param string|int $dt a date string or a unix timestamp
     * @return string
     */
    public static function datetimeToMysqldatetime($dt)
    {
        $dt = (is_string($dt)) ? strtotime($dt) : $dt;
        return date('Y-m-d H:i:s', $dt);
    }

    /**
     * @return array
     */
    public function generateChart()
    {
        $query = 'SELECT '.static::_quote_identifier('group').
            ', COUNT('.static::_quote_identifier('group').') as total FROM '.
            static::_quote_identifier(static::$_tableName).' GROUP BY '.
            static::_quote_identifier('group');

        $stmt = static::execute($query);
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        foreach ($result as &$item) {
            $item['color'] = '#'.random_color();
        }
        
        return $result;
    }

    /**
     * Retorna uma lista de grupos preexistentes no banco de dados.
     *
     * @return array
     */
    public function getGroupList()
    {
        $query = 'SELECT '.static::_quote_identifier('group').' FROM '.
            static::_quote_identifier(static::$_tableName).' GROUP BY '.
            static::_quote_identifier('group') . ' ORDER BY '.static::_quote_identifier('group');

        $stmt = static::execute($query);
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($result)) {
            return [];
        }
        
        $groups = [];
        array_walk($result, function($item) use (&$groups) {
            $groups[] = $item['group'];
        });

        return $groups;
    }
}
