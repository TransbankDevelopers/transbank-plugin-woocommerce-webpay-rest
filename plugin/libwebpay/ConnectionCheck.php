<?php

use Transbank\WooCommerce\WebpayRest\WebpayplusTransbankSdk;

class ConnectionCheck
{
    public static function check()
    {
        $resp = ConnectionCheck::setCreateTransaction();

        header('Content-Type: application/json');
        ob_clean();
        echo json_encode($resp);
        wp_die();
    }

    public static function setCreateTransaction()
    {
        $amount = 990;
        $buyOrder = '_Healthcheck_';
        $sessionId = uniqid();
        $returnUrl = 'http://test.com/test';

        $status = 'Error';
        try {
            $webpayplusTransbankSdk = new WebpayplusTransbankSdk();
            $result = $webpayplusTransbankSdk->createInner(0, $buyOrder, $sessionId, $amount, $returnUrl);
            $status = 'OK';

        } catch (\Exception $e) {
            $status = 'Error';
            $result = [
                'error'  => 'Error al crear la transacción',
                'detail' => $e->getMessage()
            ];
        }

        return [
            'status'   => ['string' => $status],
            'response' => $result
        ];
    }
}
