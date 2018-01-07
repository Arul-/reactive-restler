<?php

namespace improved;

use ArrayDB;
use DataStoreInterface;
use DB_Serialized_File;
use Luracast\Restler\HttpException;

class Authors
{
    /**
     * @var DataStoreInterface
     */
    public $dp;

    function __construct()
    {
        /**
         * $this->dp = new DB_PDO_Sqlite('db2');
         * $this->dp = new DB_PDO_MySQL('db2');
         * $this->dp = new DB_Serialized_File('db2');
         * $this->dp = new DB_Session('db2');
         * $this->dp = new ArrayDB('db2');
         */
        $class = DATA_STORE_IMPLEMENTATION;
        $this->dp = new $class('db2');
    }

    function index()
    {
        return $this->dp->getAll();
    }

    /**
     * @param int $id
     *
     * @return array
     * @throws HttpException
     */
    function get($id)
    {
        $r = $this->dp->get($id);
        if ($r === false) {
            throw new HttpException(404);
        }
        return $r;
    }

    /**
     * @status 201
     *
     * @param string $name {@from body}
     * @param string $email {@type email} {@from body}
     *
     * @return mixed
     */
    function post($name, $email)
    {
        return $this->dp->insert(compact('name', 'email'));
    }

    /**
     * @param int $id
     * @param string $name {@from body}
     * @param string $email {@type email} {@from body}
     *
     * @return mixed
     * @throws HttpException
     */
    function put($id, $name, $email)
    {
        $r = $this->dp->update($id, compact('name', 'email'));
        if ($r === false) {
            throw new HttpException(404);
        }
        return $r;
    }

    /**
     * @param int $id
     * @param string $name {@from body}
     * @param string $email {@type email} {@from body}
     *
     * @return mixed
     * @throws HttpException
     */
    function patch($id, $name = null, $email = null)
    {
        $patch = $this->dp->get($id);
        if ($patch === false) {
            throw new HttpException(404);
        }
        $modified = false;
        if (isset($name)) {
            $patch['name'] = $name;
            $modified = true;
        }
        if (isset($email)) {
            $patch['email'] = $email;
            $modified = true;
        }
        if (!$modified) {
            throw new HttpException(304); //not modified
        }
        $r = $this->dp->update($id, $patch);
        if ($r === false) {
            throw new HttpException(404);
        }
        return $r;
    }

    /**
     * @param int $id
     *
     * @return array
     * @throws HttpException
     */
    function delete($id)
    {
        $r = $this->dp->delete($id);
        if ($r === false) {
            throw new HttpException(404);
        }
        return $r;
    }
}

