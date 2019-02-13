<?php


namespace Luracast\Restler\Session;


use Exception;
use SessionHandlerInterface;
use SessionIdInterface;

class FileSessionHandler implements SessionHandlerInterface, SessionIdInterface
{
    private $savePath;
    private $data = [];

    public function __construct(string $savePath)
    {
        $this->open($savePath, '');
    }

    function open($savePath, $sessionName)
    {
        $this->savePath = $savePath;
        if (!is_dir($this->savePath)) {
            mkdir($this->savePath, 0777);
        }

        return true;
    }

    function close()
    {
        return true;
    }

    function read($id)
    {
        $this->data[$id] = (string)@file_get_contents("$this->savePath/sess_$id");
        return $this->data[$id];
    }

    function write($id, $data)
    {
        $file = "$this->savePath/sess_$id";
        if (isset($this->data[$id]) && $this->data[$id] == $data) {
            return touch($file);
        }
        return file_put_contents($file, $data) === false ? false : true;
    }

    function destroy($id)
    {
        $file = "$this->savePath/sess_$id";
        if (file_exists($file)) {
            unlink($file);
        }

        return true;
    }

    function gc($maxlifetime)
    {
        foreach (glob("$this->savePath/sess_*") as $file) {
            if (filemtime($file) + $maxlifetime < time() && file_exists($file)) {
                unlink($file);
            }
        }

        return true;
    }

    /**
     * Create session ID
     * @link https://php.net/manual/en/sessionidinterface.create-sid.php
     * @return string
     * @throws Exception
     */
    public function create_sid()
    {
        return \bin2hex(\random_bytes(32));
    }
}