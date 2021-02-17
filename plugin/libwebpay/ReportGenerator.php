<?php

namespace Transbank\Woocommerce;

use Transbank\WooCommerce\WebpayRest\Helpers\HealthCheckFactory;
use Transbank\WooCommerce\WebpayRest\Helpers\ReportPdfLog;

class ReportGenerator
{
    public static function download()
    {
        $document = filter_input(INPUT_GET, 'document', FILTER_SANITIZE_STRING);
        $healthcheck = HealthCheckFactory::create();

        $json = $healthcheck->printFullResume();
        $temp = json_decode($json);
        if ($document == 'report') {
            unset($temp->php_info);
        } else {
            $temp = ['php_info' => $temp->php_info];
        }
        $rl = new ReportPdfLog($document);
        $rl->getReport(json_encode($temp));
        wp_die();
    }
}
