<?php

namespace Transbank\WooCommerce\WebpayRest\Utils;

use Throwable;
use Transbank\WooCommerce\WebpayRest\Helpers\TbkFactory;
use Transbank\WooCommerce\WebpayRest\Helpers\DatabaseTableInstaller;

class TableCheck
{
    public static function check()
    {
        $logger = TbkFactory::createLogger();
        $transactionRepository = TbkFactory::createTransactionRepository();
        $inscriptionRepository = TbkFactory::createInscriptionRepository();
        try {
            $existTransactionTable = $transactionRepository->checkExistTable();

            if ($existTransactionTable) {
                $existInscriptionTable = $inscriptionRepository->checkExistTable();

                if (!$existInscriptionTable) {
                    DatabaseTableInstaller::createTableInscription();
                }

                $resp = self::handleResponse(
                    !$existInscriptionTable,
                    $inscriptionRepository->getRawTableName(),
                    $inscriptionRepository->getLastQueryError(),
                );
            } else {
                $resp = self::handleResponse(
                    true,
                    $transactionRepository->getRawTableName(),
                    $transactionRepository->getLastQueryError(),
                );
                DatabaseTableInstaller::createTableTransaction();
            }
        } catch (Throwable $e) {
            $logger->logInfo("Error ejecutando comprobación. Exception " . "{$e->getMessage()}");
            $resp = self::handleResponse(true, '', $e->getMessage(), "Error ejecutando comprobación.");
        }

        header('Content-Type: application/json');
        ob_clean();
        echo json_encode($resp);
        wp_die();
    }

    /**
     * Builds a standardized response array for database table validation.
     *
     * This helper centralizes the response structure used when checking
     * the existence of a database table, returning either a success or
     * error payload depending on the given flags.
     *
     * @param bool $error Indicates whether an error occurred.
     * @param string $tableName Database table name involved in the operation.
     * @param string|null $exceptionMessage Optional exception message.
     * @param string|null $errorMessage Optional human-readable error message.
     *
     * @return array{
     *     ok: bool,
     *     msg: string|null,
     *     error: string,
     *     exception: string|null
     * }
     */

    private static function handleResponse(
        bool $error = false,
        string $tableName = '',
        string $exceptionMessage = null,
        string $errorMessage = null,
    ) {
        $response = array(
            'ok' => true,
            'msg' => "La tabla '{$tableName}' existe.",
            'error' => '',
            'exception' => '',
        );

        if ($error) {
            $response['msg'] = null;
            $response['error'] = $errorMessage ?? "La tabla '{$tableName}' no se encontró en la base de datos.";
            $response['exception'] = $exceptionMessage;
        }

        return $response;
    }
}
