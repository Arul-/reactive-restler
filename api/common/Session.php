<?php

/**
 * Fake Database. All records are stored in $_SESSION
 */
class DB_Session implements DataStoreInterface
{
    function __construct()
    {
        @session_start();
        if (!isset($_SESSION['pk'])) {
            $this->install();
        }
    }

    private function pk()
    {
        return $_SESSION['pk']++;
    }

    private function find($id)
    {
        foreach ($_SESSION['rs'] as $index => $rec) {
            if ($rec['id'] == $id) {
                return $index;
            }
        }
        return false;
    }

    function get($id)
    {
        $index = $this->find($id);
        if ($index === false) {
            return false;
        }
        return $_SESSION['rs'][$index];
    }

    function getAll()
    {
        return $_SESSION['rs'];
    }

    function insert($rec)
    {
        $rec['id'] = $this->pk();
        array_push($_SESSION['rs'], $rec);
        return $rec;
    }

    function update($id, $rec, $create = true)
    {
        $index = $this->find($id);
        if (!$create && $index === false) {
            return false;
        }
        $rec['id'] = $id;
        $_SESSION['rs'][$index] = $rec;
        return $rec;
    }

    function delete($id)
    {
        $index = $this->find($id);
        if ($index === false) {
            return false;
        }
        $record = array_splice($_SESSION['rs'], $index, 1);
        return array_shift($record);
    }

    private function install()
    {
        /** install initial data **/
        $_SESSION['pk'] = 5;
        $_SESSION['rs'] = array(
            array(
                'id' => 1,
                'name' => 'Jac Wright',
                'email' => 'jacwright@gmail.com'
            ),
            array(
                'id' => 2,
                'name' => 'Arul Kumaran',
                'email' => 'arul@luracast.com'
            )
        );
    }
}

