<?php


namespace Luracast\Restler;


use InvalidArgumentException;
use Luracast\Restler\Contracts\SessionInterface;
use SessionHandlerInterface;
use SessionIdInterface;

class Session implements SessionInterface
{
    private $oldIds = [];
    private $status = PHP_SESSION_NONE;
    /**
     * @var array
     */
    private $contents = [];
    /** @var array */
    private $flash_in = [];
    /** @var array */
    private $flash_out = [];
    /**
     * @var SessionHandlerInterface
     */
    private $handler;
    /**
     * @var SessionIdInterface
     */
    private $sessionId;
    /**
     * @var string
     */
    private $id;

    public function __construct(SessionHandlerInterface $handler, SessionIdInterface $sessionId, string $id = '')
    {
        $this->handler = $handler;
        $this->sessionId = $sessionId;
        $this->id = $id;

        if ($this->id !== '') {
            $this->status = PHP_SESSION_ACTIVE;
            $data = unserialize($handler->read($id));
            $this->contents = $data['contents'] ?? [];
            $this->flash_out = $data['flash'] ?? [];
        }
    }

    function getId(): string
    {
        return $this->id;
    }

    function get(string $name)
    {
        $key = mb_strtolower($name);
        if (isset($this->contents[$key])) {
            return $this->contents[$key];
        }
        throw new InvalidArgumentException("$name does not exist");
    }

    function has(string $name): bool
    {
        $key = mb_strtolower($name);
        return isset($this->contents[$key]);
    }

    function set(string $name, $value): bool
    {
        if ($this->status !== PHP_SESSION_ACTIVE) {
            $this->start();
        }
        $key = mb_strtolower($name);
        $this->contents[$key] = $value;
        return true;
    }

    function unset(string $name): bool
    {
        $key = mb_strtolower($name);
        if (isset($this->contents[$key])) {
            unset($this->contents[$key]);
            return true;
        }
        return false;
    }

    function flash(string $name)
    {
        $key = mb_strtolower($name);
        if (isset($this->flash_out[$key])) {
            return $this->flash_out[$key];
        }
        if (isset($this->flash_in[$key])) {
            return $this->flash_in[$key];
        }
        throw new InvalidArgumentException("$name does not exist");
    }

    function hasFlash(string $name): bool
    {
        $key = mb_strtolower($name);
        return isset($this->flash_in[$key]) || isset($this->flash_out[$key]);
    }

    function setFlash(string $name, $value): bool
    {
        if ($this->status !== PHP_SESSION_ACTIVE) {
            $this->start();
        }
        $key = mb_strtolower($name);
        $this->flash_in[$key] = $value;
        return true;
    }

    function unsetFlash(string $name): bool
    {
        $key = mb_strtolower($name);
        if (isset($this->flash_in[$key])) {
            unset($this->flash_in[$key]);
            return true;
        }
        return false;
    }

    function start(array $options = []): bool
    {
        if ($this->status === PHP_SESSION_ACTIVE) {
            return true;
        }

        $this->status = PHP_SESSION_ACTIVE;

        if ($this->id === '') {
            $this->id = $this->sessionId->create_sid();
            $this->contents = [];
            $this->flash_in = [];
            $this->flash_out = [];
        }
        return true;
    }

    function regenerateId(): bool
    {
        if ($this->status !== PHP_SESSION_ACTIVE) {
            return false;
        }

        $this->oldIds[] = $this->id;
        $this->id = $this->sessionId->create_sid();

        return true;
    }

    function commit(): bool
    {
        if ($this->status !== PHP_SESSION_ACTIVE) {
            return false;
        }
        $data = ['contents' => $this->contents, 'flash' => $this->flash_in];
        return $this->handler->write($this->id, serialize($data));
    }

    function save(): bool
    {
        return $this->commit();
    }

    /**
     * @return int
     *
     * PHP_SESSION_DISABLED if sessions are disabled.
     * PHP_SESSION_NONE if sessions are enabled, but none exists.
     * PHP_SESSION_ACTIVE if sessions are enabled, and one exists.
     */
    function status(): int
    {
        return $this->status;
    }

    function destroy(): bool
    {
        if ($this->status === PHP_SESSION_NONE) {
            return true;
        }

        $this->oldIds[] = $this->id;
        $this->handler->destroy($this->id);
        $this->status = PHP_SESSION_NONE;
        $this->id = '';
        $this->contents = [];
        return true;
    }

    public function current()
    {
        return current($this->contents);
    }

    public function next()
    {
        return next($this->contents);
    }

    public function key()
    {
        return key($this->contents);
    }

    public function valid()
    {
        return key($this->contents) !== null;
    }

    public function rewind()
    {
        reset($this->contents);
    }
}
