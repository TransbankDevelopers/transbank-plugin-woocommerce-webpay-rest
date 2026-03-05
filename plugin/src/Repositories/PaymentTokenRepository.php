<?php

namespace Transbank\WooCommerce\WebpayRest\Repositories;

use wpdb;

class PaymentTokenRepository
{
    private wpdb $db;
    private string $tokensTable;
    private string $metaTable;

    public function __construct(wpdb $wpdb)
    {
        $this->db = $wpdb;
        $this->tokensTable = $wpdb->prefix . 'woocommerce_payment_tokens';
        $this->metaTable = $wpdb->prefix . 'woocommerce_payment_tokenmeta';
    }

    public function deleteById(int $tokenId): void
    {
        $this->db->delete($this->metaTable, ['payment_token_id' => $tokenId], ['%d']);
        $this->db->delete($this->tokensTable, ['token_id' => $tokenId], ['%d']);
    }

    public function findTokenIdByUserAndUsername(int $userId, string $oneclickUsername): ?int
    {
        $sql = "
            SELECT t.token_id
            FROM {$this->tokensTable} t
            INNER JOIN {$this->metaTable} m
                ON m.payment_token_id = t.token_id
            WHERE t.user_id = %d
              AND m.meta_key = 'username'
              AND m.meta_value = %s
            LIMIT 1
        ";

        $tokenId = $this->db->get_var(
            $this->db->prepare($sql, $userId, $oneclickUsername)
        );

        if ($tokenId === null) {
            return null;
        }

        $tokenId = (int) $tokenId;

        return $tokenId > 0 ? $tokenId : null;
    }
}
