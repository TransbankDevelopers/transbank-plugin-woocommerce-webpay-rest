<?php

use Transbank\WooCommerce\WebpayRest\Helpers\HealthCheckFactory;

class ConnectionCheck
{
    public static function check()
    {
        $healthCheck = HealthCheckFactory::create();

        $resp = $healthCheck->setCreateTransaction();

        header('Content-Type: application/json');
        ob_clean();
        echo json_encode($resp);
        wp_die();
    }
}
