<?php

namespace Essentio\Core;

/**
 * A custom session handler using APCu for storing session data.
 * Implements both SessionHandlerInterface and SessionUpdateTimestampHandlerInterface.
 */
class SessionHandler implements \SessionHandlerInterface, \SessionUpdateTimestampHandlerInterface
{
    /**
     * SessionHandler constructor.
     *
     * @param int $ttl
     */
    public function __construct(
        protected int $ttl = 3600
    ) {}

    /**
     * Closes the session.
     *
     * This method is invoked when the session is closed.
     *
     * @return bool
     */
    public function close(): bool
    {
        return true;
    }

    /**
     * Destroys a session.
     *
     * Deletes the session data from APCu.
     *
     * @param string $id
     * @return bool
     */
    public function destroy(string $id): bool
    {
        return \apcu_delete($this->key($id));
    }

    /**
     * Performs garbage collection for sessions.
     *
     * This method is not utilized with APCu, as APCu handles expiration internally.
     *
     * @param int $max_lifetime
     * @return int
     */
    public function gc(int $max_lifetime): int
    {
        return 0;
    }

    /**
     * Opens a session.
     *
     * Called when a session is started. No specific action is needed for APCu.
     *
     * @param string $path
     * @param string $name
     * @return bool
     */
    public function open(string $path, string $name): bool
    {
        return true;
    }

    /**
     * Reads session data.
     *
     * Retrieves the session data from APCu.
     *
     * @param string $id
     * @return string
     */
    public function read(string $id): string
    {
        return \apcu_fetch($this->key($id)) ?: '';
    }

    /**
     * Writes session data.
     *
     * Stores the session data in APCu with the configured TTL.
     *
     * @param string $id
     * @param string $data
     * @return bool
     */
    public function write(string $id, string $data): bool
    {
        return \apcu_store($this->key($id), $data, $this->ttl);
    }

    /**
     * Validates a session ID.
     *
     * Checks if session data exists in APCu for the given session ID.
     *
     * @param string $id
     * @return bool
     */
    public function validateId(string $id): bool
    {
        return \apcu_exists($this->key($id));
    }

    /**
     * Updates the session's timestamp.
     *
     * Refreshes the session data in APCu by storing it again with the current TTL.
     *
     * @param string $id
     * @param string $data
     * @return bool
     */
    public function updateTimestamp(string $id, string $data): bool
    {
        return \apcu_store($this->key($id), $data, $this->ttl);
    }

    /**
     * Generates the APCu key for a session.
     *
     * Prepends a prefix to the session ID to ensure a unique key.
     *
     * @param string $id
     * @return string
     */
    protected function key(string $id): string
    {
        return "session_" . $id;
    }
}
