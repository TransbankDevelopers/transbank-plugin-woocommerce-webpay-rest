<?php

namespace Transbank\WooCommerce\WebpayRest\Utils;

use Transbank\WooCommerce\WebpayRest\Helpers\TbkFactory;
use Transbank\Webpay\Options;

class ConnectionCheck
{
    private const ALLOWED_PRODUCTS = ['webpay', 'oneclick'];

    public static function check()
    {
        $product = sanitize_text_field($_POST['product'] ?? 'webpay');

        if (!in_array($product, self::ALLOWED_PRODUCTS, true)) {
            TbkFactory::createLogger()->logError('Producto inválido recibido en prueba de conexión.', [
                'product' => $product,
            ]);

            header('Content-Type: application/json');
            ob_clean();
            echo json_encode(static::buildErrorResponse('No disponible'));
            wp_die();
        }

        $resp = static::performProductTestTransaction($product);

        header('Content-Type: application/json');
        ob_clean();
        echo json_encode($resp);
        wp_die();
    }

    private static function performProductTestTransaction(string $product)
    {
        if ($product === 'webpay') {
            return static::performWebpayTestTransaction();
        }

        if ($product === 'oneclick') {
            return static::performOneclickTestTransaction();
        }

        return [
            'status'   => [
                'string' => 'Error',
            ],
            'meta' => [
                'environmentLabel' => 'No disponible',
            ],
        ];
    }

    public static function performWebpayTestTransaction()
    {
        $amount = 990;
        $returnUrl = 'http://test.com/test';
        $environment = TbkFactory::getWebpayplusConfig()->getEnvironment();
        $environmentLabel = static::getEnvironmentLabel($environment);
        $logger = TbkFactory::createWebpayPlusLogger();

        $logger->logInfo('Iniciando prueba de conexión Webpay Plus.', [
            'environment' => $environmentLabel,
        ]);

        try {
            $webpayService = TbkFactory::createWebpayService();
            $webpayService->createTransaction(0, $amount, $returnUrl);

            $logger->logInfo('Prueba de conexión Webpay Plus exitosa.', [
                'environment' => $environmentLabel,
            ]);

            return [
                'status'   => [
                    'string' => 'OK',
                ],
                'meta' => [
                    'environmentLabel' => $environmentLabel,
                ],
            ];
        } catch (\Throwable $e) {
            $logger->logError('Prueba de conexión Webpay Plus fallida.', [
                'environment' => $environmentLabel,
                'error' => $e->getMessage(),
            ]);

            return [
                'status'   => [
                    'string' => 'Error',
                ],
                'meta' => [
                    'environmentLabel' => $environmentLabel,
                ],
            ];
        }
    }

    public static function performOneclickTestTransaction()
    {
        $returnUrl = 'http://test.com/test';
        $testUserName = 'tbk_connection_test_' . wp_generate_password(8, false, false);
        $testEmail = 'tbk-connection-test@example.com';
        $environment = TbkFactory::getOneclickConfig()->getEnvironment();
        $environmentLabel = static::getEnvironmentLabel($environment);
        $logger = TbkFactory::createOneclickLogger();

        $logger->logInfo('Iniciando prueba de conexión Webpay Oneclick.', [
            'environment' => $environmentLabel,
        ]);

        try {
            $oneclickInscriptionService = TbkFactory::createOneclickInscriptionService();
            $oneclickInscriptionService->startInscription($testUserName, $testEmail, $returnUrl);

            $logger->logInfo('Prueba de conexión Webpay Oneclick exitosa.', [
                'environment' => $environmentLabel,
            ]);

            return [
                'status'   => [
                    'string' => 'OK',
                ],
                'meta' => [
                    'environmentLabel' => $environmentLabel,
                ],
            ];
        } catch (\Exception $e) {
            $logger->logError('Prueba de conexión Webpay Oneclick fallida.', [
                'environment' => $environmentLabel,
                'error' => $e->getMessage(),
            ]);

            return [
                'status'   => [
                    'string' => 'Error',
                ],
                'meta' => [
                    'environmentLabel' => $environmentLabel,
                ],
            ];
        }
    }

    private static function getEnvironmentLabel(string $environment): string
    {
        if ($environment === Options::ENVIRONMENT_PRODUCTION) {
            return 'Producción';
        }

        return 'Integración';
    }

    private static function buildErrorResponse(string $environmentLabel): array
    {
        return [
            'status'   => [
                'string' => 'Error',
            ],
            'meta' => [
                'environmentLabel' => $environmentLabel,
            ],
        ];
    }
}
