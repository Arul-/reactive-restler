<?php

/**
 * Flat File DB. All data is serialized and stored in store folder
 * This file will be automatically created when missing
 * Make sure this folder has sufficient write permission
 * for this script to create the file.
 */
class SerializedFileDataProvider implements DataStoreInterface
{
    private $arr;
    private $modified = false;
    private static $folder;
    private $file;

    function __construct(string $name)
    {
        if (empty(static::$folder)) {
            static::$folder = __DIR__ . DIRECTORY_SEPARATOR . 'store' . DIRECTORY_SEPARATOR;
        }
        $this->file = $file = static::$folder . $name . '.serialized.php';
        /** load data from file **/
        if (file_exists($file)) {
            $this->arr = require $file;
        } else {
            $this->install();
        }
    }

    function __destruct()
    {
        if ($this->modified) {
            /** save data **/
            $content = "<?php\n";
            $content .= 'return ' . var_export($this->arr, true) . ';';
            file_put_contents($this->file, $content);
        }
    }

    private function pk()
    {
        return $this->arr['pk']++;
    }

    private function find($id)
    {
        foreach ($this->arr['rs'] as $index => $rec) {
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
        return $this->arr['rs'][$index];
    }

    function getAll()
    {
        return $this->arr['rs'];
    }

    function insert($rec)
    {
        $rec['id'] = $this->pk();
        $this->modified = true;
        array_push($this->arr['rs'], $rec);
        return $rec;
    }

    function update($id, $rec, $create = true)
    {
        $index = $this->find($id);
        if (!$create && $index === false) {
            return false;
        }
        $rec['id'] = $id;
        $this->modified = true;
        $this->arr['rs'][$index] = $rec;
        return $rec;
    }

    function delete($id)
    {
        $index = $this->find($id);
        if ($index === false) {
            return false;
        }
        $this->modified = true;
        return array_shift(array_splice($this->arr['rs'], $index, 1));
    }

    private function install()
    {
        /** install initial data **/
        $this->arr = array();
        $this->arr['rs'] = array(
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
        $this->arr['pk'] = 5;
        $this->modified = true;
    }

    static function reset()
    {
        foreach (glob(static::$folder . "*.serialized.php") as $filename) {
            unlink($filename);
        }
    }
}

