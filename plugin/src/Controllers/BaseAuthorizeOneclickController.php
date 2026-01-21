<?php

namespace Transbank\WooCommerce\WebpayRest\Controllers;

use WC_Order;
use Transbank\Plugin\Exceptions\EcommerceException;
use Transbank\WooCommerce\WebpayRest\Helpers\TbkFactory;
use Transbank\WooCommerce\WebpayRest\Helpers\TbkResponseUtil;
use Transbank\Webpay\Oneclick\Responses\MallTransactionAuthorizeResponse;
use Transbank\WooCommerce\WebpayRest\Services\TransactionService;
use Transbank\WooCommerce\WebpayRest\Services\OneclickAuthorizationService;
use Transbank\Plugin\Helpers\PluginLogger;
use Transbank\WooCommerce\WebpayRest\Services\EcommerceService;

abstract class BaseAuthorizeOneclickController
{
    protected PluginLogger $log;
    protected TransactionService $transactionService;
    protected OneclickAuthorizationService $oneclickAuthorizationService;
    protected EcommerceService $ecommerceService;

    /**
     * Initializes the base controller.
     */
    public function __construct()
    {
        $this->transactionService = TbkFactory::createTransactionService();
        $this->oneclickAuthorizationService = TbkFactory::createOneclickAuthorizationService();
        $this->ecommerceService = TbkFactory::createEcommerceService();
        $this->log = TbkFactory::createOneclickLogger();
    }

    protected function authorizeTransaction($orderId, $amount, $paymentToken): MallTransactionAuthorizeResponse
    {
        $transactionData = $this->oneclickAuthorizationService->prepareTransaction(
            $orderId,
            $amount
        );
        $transaction = $this->transactionService->createAndGet($transactionData);

        $this->log->logInfo('Autorizando transacción', [
            'userName' => $paymentToken->get_username(),
            'tbkUser' => $paymentToken->get_token(),
            'buyOrder' => $transaction->getBuyOrder(),
            'childBuyOrder' => $transaction->getChildBuyOrder(),
            'amount' => $transaction->getAmount()
        ]);
        $authorizeResponse = $this->oneclickAuthorizationService->authorize(
            $paymentToken->get_username(),
            $paymentToken->get_token(),
            $transaction->getBuyOrder(),
            $transaction->getChildBuyOrder(),
            $transaction->getAmount()
        );

        $this->log->logInfo('Respuesta autorizacion Tbk', ['status' => $authorizeResponse->getDetails()[0]->getStatus()]);



        $this->transactionService->updateWithAuthorizeResponse($transaction->getId(), $authorizeResponse);
        return $authorizeResponse;
    }

    protected function handleFailedAuthorization(WC_Order $order, $transaction, $authorizeResponse)
    {
        $details = $authorizeResponse->getDetails()[0] ?? null;
        $status = $details?->getStatus();
        $responseCode = $details?->getResponseCode();
        $responseJson = json_encode($authorizeResponse);

        $this->log->logError(
            "Transacción con autorización rechazada",
            [
                'parentBuyOrder' => $authorizeResponse->getBuyOrder(),
                'responseCode' => $responseCode
            ]
        );

        $orderNotes = $this->getOrderNotesFromAuthorizeResponse($authorizeResponse, 'Oneclick: Pago rechazado');
        $order->add_order_note($orderNotes);
        $order->add_meta_data('transbank_response', $responseJson);

        if ($status === 'CONSTRAINTS_VIOLATED') {
            $message = 'La transacción ha sido rechazada porque se superó el monto máximo por transacción, el monto máximo diario o el número de transacciones diarias configuradas por el comercio para cada usuario';
        } else {
            $message = "La transacción ha sido rechazada (Código de error: $responseCode)";
        }

        throw new EcommerceException($message);
    }

    protected function getOrderNotesFromAuthorizeResponse(MallTransactionAuthorizeResponse $response, string $orderNotesTitle)
    {
        $firstDetail = $response->getDetails()[0];
        $formattedAmount = TbkResponseUtil::getAmountFormatted($firstDetail->getAmount());
        $status = TbkResponseUtil::getStatus($firstDetail->getStatus());
        $paymentType = TbkResponseUtil::getPaymentType($firstDetail->getPaymentTypeCode());
        $installmentType = TbkResponseUtil::getInstallmentType($firstDetail->getPaymentTypeCode());
        $formattedAccountingDate = TbkResponseUtil::getAccountingDate($response->getAccountingDate());
        $formattedDate = TbkResponseUtil::transactionDateToLocalDate($response->getTransactionDate());
        $installmentAmount = $firstDetail->getInstallmentsAmount() ?? 0;
        $formattedInstallmentAmount = TbkResponseUtil::getAmountFormatted($installmentAmount);

        return "
            <div class='transbank_response_note'>
                <p><h3>{$orderNotesTitle}</h3></p>

                <strong>Estado: </strong>{$status} <br />
                <strong>Orden de compra mall: </strong>{$response->getBuyOrder()} <br />
                <strong>Orden de compra tienda: </strong>{$firstDetail->getBuyOrder()} <br />
                <strong>Código de autorización: </strong>{$firstDetail->getAuthorizationCode()} <br />
                <strong>Últimos dígitos tarjeta: </strong>{$response->getCardNumber()} <br />
                <strong>Monto: </strong>{$formattedAmount} <br />
                <strong>Código de respuesta: </strong>{$firstDetail->getResponseCode()} <br />
                <strong>Tipo de pago: </strong>{$paymentType} <br />
                <strong>Tipo de cuota: </strong>{$installmentType} <br />
                <strong>Número de cuotas: </strong>{$firstDetail->getInstallmentsNumber()} <br />
                <strong>Monto de cada cuota: </strong>{$formattedInstallmentAmount} <br />
                <strong>Fecha:</strong> {$formattedDate} <br />
                <strong>Fecha contable:</strong> {$formattedAccountingDate} <br />
            </div>
        ";
    }
}
