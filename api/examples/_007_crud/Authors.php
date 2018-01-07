<?php
class Authors
{
    /**
     * @var DataStoreInterface
     */
    public $dp;

    static $FIELDS = array('name', 'email');

    function __construct()
    {
        /**
         * $this->dp = new DB_PDO_Sqlite('db1');
         * $this->dp = new DB_PDO_MySQL('db1');
         * $this->dp = new DB_Serialized_File('db1');
         * $this->dp = new DB_Session('db1');
         * $this->dp = new ArrayDB('db1');
         */
        $class = DATA_STORE_IMPLEMENTATION;
        $this->dp = new $class('db1');
    }

    function index()
    {
        return $this->dp->getAll();
    }

    function get($id)
    {
        return $this->dp->get($id);
    }

    function post($request_data = NULL)
    {
        return $this->dp->insert($this->_validate($request_data));
    }

    function put($id, $request_data = NULL)
    {
        return $this->dp->update($id, $this->_validate($request_data));
    }

    function delete($id)
    {
        return $this->dp->delete($id);
    }

    private function _validate($data)
    {
        $author = array();
        foreach (authors::$FIELDS as $field) {
//you may also validate the data here
            if (!isset($data[$field]))
                throw new RestException(400, "$field field missing");
            $author[$field] = $data[$field];
        }
        return $author;
    }
}

