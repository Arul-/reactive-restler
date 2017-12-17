<?php


class ArrayDB implements iStoreData
{

    public static $data = [];

    /**
     * ArrayDB constructor.
     */
    public function __construct()
    {
        if (empty(static::$data)) {
            $this->install();
        }
    }

    private function pk()
    {
        return static::$data['pk']++;
    }

    private function find($id)
    {
        foreach (static::$data['rs'] as $index => $rec) {
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
        return static::$data['rs'][$index];
    }

    function getAll()
    {
        return static::$data['rs'];
    }

    function insert($rec)
    {
        $rec['id'] = $this->pk();
        array_push(static::$data['rs'], $rec);
        return $rec;
    }

    function update($id, $rec)
    {
        $index = $this->find($id);
        if ($index === false) {
            return false;
        }
        $rec['id'] = $id;
        static::$data['rs'][$index] = $rec;
        return $rec;
    }

    function delete($id)
    {
        $index = $this->find($id);
        if ($index === false) {
            return false;
        }
        $record = array_splice(static::$data['rs'], $index, 1);
        return array_shift($record);
    }

    private function install()
    {
        /** install initial data **/
        static::$data['pk'] = 5;
        static::$data['rs'] = [
            [
                'id' => 1,
                'name' => 'Jac Wright',
                'email' => 'jacwright@gmail.com'
            ],
            [
                'id' => 2,
                'name' => 'Arul Kumaran',
                'email' => 'arul@luracast.com'
            ]
        ];
    }
}