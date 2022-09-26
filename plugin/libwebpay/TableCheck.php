<?php

use Transbank\WooCommerce\WebpayRest\Helpers\LogHandler;
use Transbank\WooCommerce\WebpayRest\Models\Transaction;
use Transbank\WooCommerce\WebpayRest\Models\Inscription;
use Transbank\WooCommerce\WebpayRest\Helpers\DatabaseTableInstaller;

class TableCheck
{
    public static function check()
    {
        try {
            $resp = Transaction::checkExistTable();
            if ($resp['ok']){
                $resp = Inscription::checkExistTable();
                if (!$resp['ok']){
                    DatabaseTableInstaller::createTableInscription();
                }
            }
            else{
                DatabaseTableInstaller::createTableTransaction();
            }
        }
        catch(Exception $e) {
            (new LogHandler())->logInfo("Error ejecutando comprobación. Exception "."{$e->getMessage()}");
            $resp = array('ok' => false, 'error' => "Error ejecutando comprobación.", 'exception' => "{$e->getMessage()}");
        }

        header('Content-Type: application/json');
        ob_clean();
        echo json_encode($resp);
        wp_die();
    }
}
