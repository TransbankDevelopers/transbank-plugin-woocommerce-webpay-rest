<?php

namespace Transbank\Woocommerce;

use Transbank\WooCommerce\WebpayRest\Helpers\HealthCheckFactory;
use Transbank\WooCommerce\WebpayRest\Helpers\LogHandler;

class ReportGenerator
{
    public static function showDiagReport()
    {
        $healthcheck = HealthCheckFactory::create();
        $myJSON = $healthcheck->printFullResume();

        $loghandler = new LogHandler();
        $json = json_decode($loghandler->getLastLog(), true);
        $obj = json_decode($myJSON, true);
        $obj['php_info'] = null;
        if (isset($json['log_content'])) {
            $html = str_replace("\r\n", '*****123', $json['log_content']);
            $html = str_replace("\n", '*****123', $json['log_content']);
            $obj += ['logs' => ['log' => explode('*****123', $html)]];
        }
        header('Content-disposition: attachment; filename=tbk-webpay-plus.json');
        header('Content-type: application/json');
        echo json_encode($obj);
    }

    public static function showPhpInfoReport()
    {
        ob_start();
        phpinfo();
        $info = ob_get_contents();
        ob_end_clean();
        echo $info;
    }
}



