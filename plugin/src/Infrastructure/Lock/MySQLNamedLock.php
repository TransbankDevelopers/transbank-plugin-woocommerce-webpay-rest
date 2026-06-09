<?php

namespace Transbank\WooCommerce\WebpayRest\Infrastructure\Lock;

use wpdb;

/**
 * Thin MySQL named lock adapter.
 *
 * It provides a reusable acquire/release primitive for flows that need to serialize work by key.
 */
class MySqlNamedLock
{
    private const LOCK_PREFIX = 'transbank_webpay_lock_';

    private wpdb $db;

    public function __construct(wpdb $wpdb)
    {
        $this->db = $wpdb;
    }

    public function acquire(string $key): bool
    {
        $lockName = $this->buildLockName($key);
        $query = $this->db->prepare('SELECT GET_LOCK(%s, 0)', $lockName);

        return (string) $this->db->get_var($query) === '1';
    }

    public function release(string $key): void
    {
        $lockName = $this->buildLockName($key);
        $query = $this->db->prepare('SELECT RELEASE_LOCK(%s)', $lockName);
        $this->db->get_var($query);
    }

    private function buildLockName(string $key): string
    {
        return self::LOCK_PREFIX . substr(hash('sha256', $key), 0, 40);
    }
}
