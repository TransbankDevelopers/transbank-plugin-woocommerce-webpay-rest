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
    private const MAX_LOCK_NAME_LENGTH = 64;

    private wpdb $db;

    public function __construct(wpdb $wpdb)
    {
        $this->db = $wpdb;
    }

    public function acquire(string $key): bool
    {
        $this->validateKeyLength($key);
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
        $this->validateKeyLength($key);
        $query = $this->db->prepare('SELECT RELEASE_LOCK(%s)', $key);
        $result = $this->db->get_var($query);

        if ($result === null) {
            throw new MySqlNamedLockException(
                'No se pudo liberar el lock de retorno de Webpay: error al consultar MySQL.'
            );
        }

        return $result === '1';
    }

    private function validateKeyLength(string $key): void
    {
        if (strlen($key) > self::MAX_LOCK_NAME_LENGTH) {
            throw new MySqlNamedLockException(
                'El nombre del lock excede el límite de ' . self::MAX_LOCK_NAME_LENGTH . ' caracteres de MySQL.'
            );
        }
    }
}
