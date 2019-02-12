<?php


namespace Luracast\Restler\Contracts;

use Iterator;
use SessionHandlerInterface;
use SessionIdInterface;

interface SessionInterface extends Iterator
{
    function get(string $name);

    function has(string $name): bool;

    function set(string $name, $value): bool;

    function unset(string $name): bool;

    function flash(string $name);

    function hasFlash(string $name): bool;

    function setFlash(string $name, $value): bool;

    function unsetFlash(string $name): bool;

    function __construct(SessionHandlerInterface $handler, SessionIdInterface $sessionId, string $id = '');

    function getId(): string;

    function start(array $options = []): bool;

    function regenerateId(): bool;

    function commit(): bool;

    function save(): bool;

    /**
     * @return int
     *
     * PHP_SESSION_DISABLED if sessions are disabled.
     * PHP_SESSION_NONE if sessions are enabled, but none exists.
     * PHP_SESSION_ACTIVE if sessions are enabled, and one exists.
     */
    function status(): int;

    function destroy(): bool;
}