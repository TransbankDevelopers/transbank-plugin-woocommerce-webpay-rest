<?php

namespace Transbank\WooCommerce\WebpayRest\Controllers;

use Transbank\WooCommerce\WebpayRest\Helpers\TbkFactory;
use Transbank\WooCommerce\WebpayRest\Models\Transaction;
use Transbank\WooCommerce\WebpayRest\Helpers\TbkResponseUtil;

class TransactionStatusController
{
    const HTTP_OK = 200;
    const HTTP_UNPROCESSABLE_ENTITY = 422;
    public function getStatus()
    {
        $response = [
            'body' => [
                'message' => 'No se pudo obtener el estado de la transacción'
            ],
            'code' => self::HTTP_UNPROCESSABLE_ENTITY
        ];
        // Check for nonce security
        $nonce = sanitize_text_field($_POST['nonce']);

        if (!wp_verify_nonce($nonce, 'my-ajax-nonce')) {
            wp_send_json($response['body'], $response['code']);
            return;
        }

        $orderId = filter_input(INPUT_POST, 'order_id', FILTER_DEFAULT);
        $orderId = htmlspecialchars($orderId, ENT_QUOTES, 'UTF-8');
        $buyOrder = filter_input(INPUT_POST, 'buy_order', FILTER_DEFAULT);
        $buyOrder = htmlspecialchars($buyOrder, ENT_QUOTES, 'UTF-8');
        $token = filter_input(INPUT_POST, 'token', FILTER_DEFAULT);
        $token = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');

        try {
            $transaction = Transaction::getApprovedByOrderId($orderId);
            if (!$transaction) {
                $response = [
                    'body' => [
                        'message' => 'No hay transacciones webpay aprobadas para esta orden'
                    ],
                    'code' => self::HTTP_UNPROCESSABLE_ENTITY
                ];
            }

            if ($transaction->product == Transaction::PRODUCT_WEBPAY_ONECLICK) {
                if ($transaction->buy_order !== $buyOrder) {
                    $response = [
                        'body' => [
                            'message' => 'El buy_order enviado y el buy_order de la transacción no coinciden'
                        ],
                        'code' => self::HTTP_UNPROCESSABLE_ENTITY
                    ];
                }

                $response = [
                    'body' => $this->getStatusForOneclickTransaction($orderId, $buyOrder),
                    'code' => self::HTTP_OK
                ];
            }

            if ($transaction->token !== $token) {
                $response = [
                    'body' => [
                        'message' => 'El token enviado y el token de la transacción no coinciden'
                    ],
                    'code' => self::HTTP_UNPROCESSABLE_ENTITY
                ];
            }

            $response = [
                'body' => $this->getStatusForWebpayTransaction($orderId, $token),
                'code' => self::HTTP_OK
            ];

            wp_send_json($response['body'], $response['code']);
        } catch (\Exception $e) {
            wp_send_json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    private function getStatusForWebpayTransaction(string $orderId, string $token)
    {
        $webpayplusTransbankSdk = TbkFactory::createWebpayplusTransbankSdk();
        $resp = $webpayplusTransbankSdk->status($orderId, $token);
        $formattedDate = TbkResponseUtil::transactionDateToLocalDate($resp->getTransactionDate());
        $modifiedResponse = clone $resp;
        $modifiedResponse->setTransactionDate($formattedDate);

        return [
            'product' => Transaction::PRODUCT_WEBPAY_PLUS,
            'status'  => $modifiedResponse,
            'raw'     => $resp,
        ];
    }

    private function getStatusForOneclickTransaction(string $orderId, string $buyOrder)
    {
        $oneclickTransbankSdk = TbkFactory::createOneclickTransbankSdk();
        $status = $oneclickTransbankSdk->status($orderId, $buyOrder);
        $statusArray = json_decode(json_encode($status), true);
        $firstDetail = json_decode(json_encode($status->getDetails()[0]), true);

        $response = array_merge($statusArray, $firstDetail);
        $formattedDate = TbkResponseUtil::transactionDateToLocalDate($status->getTransactionDate());
        $response['transactionDate'] = $formattedDate;
        unset($response['details']);

        return [
            'product' => Transaction::PRODUCT_WEBPAY_ONECLICK,
            'status'  => $response,
            'raw'     => $status,
        ];
    }
}
