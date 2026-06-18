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
    private const GET_LOCK_TIMEOUT_SECONDS = 10;

    private wpdb $db;

    public function __construct(wpdb $wpdb)
    {
        $this->db = $wpdb;
    }

    public function acquire(string $key): bool
    {
        $query = $this->db->prepare('SELECT GET_LOCK(%s, %d)', $key, self::GET_LOCK_TIMEOUT_SECONDS);
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
        $query = $this->db->prepare('SELECT RELEASE_LOCK(%s)', $key);
        $result = $this->db->get_var($query);

        if ($result === null) {
            throw new MySqlNamedLockException(
                'No se pudo liberar el lock de retorno de Webpay: error al consultar MySQL.'
            );
        }

        return $result === '1';
    }
}
