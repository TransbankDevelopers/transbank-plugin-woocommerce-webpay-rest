<?php

namespace Transbank\WooCommerce\WebpayRest\Infrastructure\Lock;

use wpdb;
use Transbank\WooCommerce\WebpayRest\Exceptions\MySqlNamedLockException;

/**
 * Thin MySQL named lock adapter.
 *
 * It provides a reusable acquire/release primitive for flows that need to serialize work by key.
 */
class MySqlNamedLock
{
    private const LOCK_PREFIX = 'transbank_webpay_lock_';
    private const GET_LOCK_TIMEOUT_SECONDS = 10;

    private wpdb $db;

    public function __construct(wpdb $wpdb)
    {
        $this->db = $wpdb;
    }

    public function acquire(string $key): bool
    {
        $lockName = $this->buildLockName($key);
        $query = $this->db->prepare('SELECT GET_LOCK(%s, %d)', $lockName, self::GET_LOCK_TIMEOUT_SECONDS);
        $result = $this->db->get_var($query);

        if ($result === null) {
            throw new MySqlNamedLockException(
                'No se pudo adquirir el lock de retorno de Webpay: error al consultar MySQL.'
            );
        }

        return $result === '1';
    }

    public function release(string $key): bool
    {
        $lockName = $this->buildLockName($key);
        $query = $this->db->prepare('SELECT RELEASE_LOCK(%s)', $lockName);
        $result = $this->db->get_var($query);

        if ($result === null) {
            throw new MySqlNamedLockException(
                'No se pudo liberar el lock de retorno de Webpay: error al consultar MySQL.'
            );
        }

        return $result === '1';
    }

    private function buildLockName(string $key): string
    {
        return self::LOCK_PREFIX . substr(hash('sha256', $key), 0, 40);
    }
}
