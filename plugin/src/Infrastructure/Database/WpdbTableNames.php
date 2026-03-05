<?php

namespace Transbank\WooCommerce\WebpayRest\Infrastructure\Database;

use wpdb;

class WpdbTableNames
{
    private wpdb $db;

    public function __construct(wpdb $db)
    {
        $this->db = $db;
    }

    public function getUsersTableName(): string
    {
        return $this->db->users;
    }
}
