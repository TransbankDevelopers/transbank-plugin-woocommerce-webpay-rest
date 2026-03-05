<?php
namespace Transbank\WooCommerce\WebpayRest\Utils;

use Transbank\WooCommerce\WebpayRest\Helpers\TbkFactory;

class ConnectionCheck
{
    public static function check()
    {
        $resp = ConnectionCheck::performTestTransaction();

        header('Content-Type: application/json');
        ob_clean();
        echo json_encode($resp);
        wp_die();
    }

    public static function performTestTransaction()
    {
        $amount = 990;
        $returnUrl = 'http://test.com/test';

        $status = 'Error';
        try {
            $webpayService = TbkFactory::createWebpayService();
            $result = $webpayService->createTransaction(0, $amount, $returnUrl);
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
            'response' => [
                'token' => $result->getToken(),
                'url'   => $result->getUrl()
            ]
        ];
    }
}
