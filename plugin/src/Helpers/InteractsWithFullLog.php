<?php

namespace Transbank\WooCommerce\WebpayRest\Helpers;

use Transbank\WooCommerce\WebpayRest\Helpers\LogHandler;
 
class InteractsWithFullLog {

    public function __construct()
    {
        $this->log = new LogHandler();
    }

    public function logWebpayPlusInstallConfigLoad($webpayCommerceCode, $webpayDefaultOrderStateIdAfterPayment){
        $this->log->logInfo('Configuración de WEBPAY PLUS se cargo de forma correcta');
        $this->log->logInfo('webpayCommerceCode: '.$webpayCommerceCode.', webpayDefaultOrderStateIdAfterPayment: '.$webpayDefaultOrderStateIdAfterPayment);
    }

    public function logWebpayPlusInstallConfigLoadDefault(){
        $this->log->logInfo('Configuración por defecto de WEBPAY PLUS se cargo de forma correcta');
    }

    public function logWebpayPlusInstallConfigLoadDefaultPorIncompleta(){
        $this->log->logInfo('Configuración por defecto de WEBPAY PLUS se cargo de forma correcta porque los valores de producción estan incompletos');
    }

    /* Logs para la instalación ONECLICK */

    public function logOneclickInstallConfigLoad($oneclickMallCommerceCode, $oneclickChildCommerceCode, $oneclickDefaultOrderStateIdAfterPayment){
        $this->log->logInfo('Configuración de ONECLICK se cargo de forma correcta');
        $this->log->logInfo('oneclickMallCommerceCode: '.$oneclickMallCommerceCode.', oneclickChildCommerceCode: '.$oneclickChildCommerceCode.', oneclickDefaultOrderStateIdAfterPayment: '.$oneclickDefaultOrderStateIdAfterPayment);
    }

    public function logOneclickInstallConfigLoadDefault(){
        $this->log->logInfo('Configuración por defecto de ONECLICK se cargo de forma correcta');
    }

    public function logOneclickInstallConfigLoadDefaultPorIncompleta(){
        $this->log->logInfo('Configuración por defecto de ONECLICK se cargo de forma correcta porque los valores de producción estan incompletos');
    }

    /* LOGS PARA WEBPAY PLUS */

    public function logWebpayPlusConfigError(){
        $this->log->logError('Configuración de WEBPAY PLUS incorrecta, revise los valores');
    }

    public function logWebpayPlusIniciando(){
        $this->log->logInfo('B.1. Iniciando medio de pago Webpay Plus');
    }

    public function logWebpayPlusAntesCrearTx($amount, $sessionId, $buyOrder, $returnUrl){
        $this->log->logInfo('B.2. Preparando datos antes de crear la transacción en Transbank');
        $this->log->logInfo('amount: '.$amount.', sessionId: '.$sessionId.', buyOrder: '.$buyOrder.', returnUrl: '.$returnUrl);
    }

    public function logWebpayPlusDespuesCrearTx($result){
        $this->log->logInfo('B.3. Transacción creada en Transbank');
        $this->log->logInfo(json_encode($result));
    }

    public function logWebpayPlusDespuesCrearTxError($result){
        $this->log->logError('B.3. Transacción creada con error en Transbank');
        $this->log->logError(json_encode($result));
    }

    public function logWebpayPlusAntesCrearTxEnTabla($transaction){
        $this->log->logInfo('B.4. Preparando datos antes de crear la transacción en la tabla webpay_transactions');
        $this->log->logInfo(json_encode($transaction));
    }

    public function logWebpayPlusDespuesCrearTxEnTabla($transaction){
        $this->log->logInfo('B.5. Transacción creada en la tabla webpay_transactions');
        $this->log->logInfo(json_encode($transaction));
    }

    public function logWebpayPlusDespuesCrearTxEnTablaError($transaction){
        $this->log->logError('B.5. Transacción no se pudo crear en la tabla webpay_transactions => ');
        $this->log->logError(json_encode($transaction));
    }

    public function logWebpayPlusRetornandoDesdeTbk($method, $params){
        $this->log->logInfo('C.1. Iniciando validación luego de redirección desde tbk => method: '.$method);
        $this->log->logInfo(json_encode($params));
    }
    
    public function logWebpayPlusDespuesObtenerTx($token, $tx){
        $this->log->logInfo('C.2. Tx obtenido desde la tabla webpay_transactions => token: '.$token);
        $this->log->logInfo(json_encode($tx));
    }

    public function logWebpayPlusRetornandoDesdeTbkFujo2Error($tbkIdSesion){
        $this->log->logError('C.2. Error tipo Flujo 2: El pago fue anulado por tiempo de espera => tbkIdSesion: '.$tbkIdSesion);
    }

    public function logWebpayPlusRetornandoDesdeTbkFujo3Error($tbktoken){
        $this->log->logError('C.2. Error tipo Flujo 3: El pago fue anulado por el usuario => tbktoken: '.$tbktoken);
    }
    public function logWebpayPlusRetornandoDesdeTbkFujo3TxError($tbktoken, $webpayTransaction){
        $this->log->logError('C.2. Error tipo Flujo 3 => tbktoken: '.$tbktoken);
        $this->log->logError(json_encode($webpayTransaction));
    }

    public function logWebpayPlusRetornandoDesdeTbkFujo4Error($tokenWs, $tbktoken){
        $this->log->logError('C.2. Error tipo Flujo 4: El pago es inválido  => tokenWs: '.$tokenWs.', tbktoken: '.$tbktoken);
    }

    public function logWebpayPlusAntesCommitTx($token, $tx){
        $this->log->logInfo('C.3. Transaccion antes del commit  => token: '.$token);
        $this->log->logInfo(json_encode($tx));
    }

    public function logWebpayPlusCommitTxYaAprobadoError($token, $tx){
        $this->log->logError('C.3. Transacción ya estaba aprobada => token: '.$token);
        $this->log->logError(json_encode($tx));
    }

    public function logWebpayPlusCommitTxNoInicializadoError($token, $tx){
        $this->log->logError('C.3. Transacción se encuentra en estado rechazado o cancelado => token: '.$token);
        $this->log->logError(json_encode($tx));
    }

    public function logWebpayPlusCommitTxCarroAprobadoError($token, $tx){
        $this->log->logError('C.3. El carro de compras ya fue pagado con otra Transacción => token: '.$token);
        $this->log->logError(json_encode($tx));
    }

    public function logWebpayPlusCommitTxCarroManipuladoError($token, $tx){
        $this->log->logError('C.3. El carro de compras ya fue pagado con otra Transacción => token: '.$token);
        $this->log->logError(json_encode($tx));
    }
    
    public function logWebpayPlusDespuesCommitTx($token, $result){
        $this->log->logInfo('C.4. Transacción con commit en Transbank => token: '.$token);
        $this->log->logInfo(json_encode($result));
        if (!is_array($result) && isset($result->buyOrder) && $result->responseCode === 0){
            $this->log->logInfo('***** COMMIT TBK OK *****');
            $this->log->logInfo('TRANSACCION VALIDADA POR TBK => TOKEN: '.$token);
            $this->log->logInfo('SI NO SE ENCUENTRA VALIDACION POR WooCommerce DEBE ANULARSE');
        }
    }

    public function logWebpayPlusGuardandoCommitExitoso($token){
        $this->log->logInfo('C.5. Transacción con commit exitoso en Transbank y guardado => token: '.$token);
    }

    public function logWebpayPlusGuardandoCommitError($token, $result){
        $this->log->logError('C.5. No se pudo guardar en base de datos el resultado del commit => token: '.$token);
        $this->log->logError(json_encode($result));
    }

    public function logWebpayPlusCommitFallidoError($token, $result){
        $this->log->logError('C.5. Respuesta de tbk commit fallido => token: '.$token);
        $this->log->logError(json_encode($result));
    }

    public function logWebpayPlusAntesValidateOrderWooCommerce($token, $amount, $cartId, $OkStatus, $currencyId, $customerSecureKey){
        $this->log->logInfo('C.6. Procesando pago - antes de validateOrder');
        $this->log->logInfo('token : '.$token.', amount : '.$amount.', cartId: '.$cartId.', OKStatus: '.$OkStatus.', currencyId: '.$currencyId.', customer_secure_key: '.$customerSecureKey);
    }

    public function logWebpayPlusDespuesValidateOrderWooCommerce($token){
        $this->log->logInfo('C.7. Procesando pago despues de validateOrder => token: '.$token);
    }

    public function logWebpayPlusTodoOk($token, $webpayTransaction){
        $this->log->logInfo('***** TODO OK *****');
        $this->log->logInfo('TRANSACCION VALIDADA POR WooCommerce Y POR TBK EN ESTADO STATUS_APPROVED => TOKEN: '.$token);
        $this->log->logInfo(json_encode($webpayTransaction));
    }

    public function logPrintCart($cart){
        $this->log->logInfo(json_encode($cart));
    }


    /* LOGS PARA ONECLICK */

    public function logOneclickConfigError(){
        $this->log->logError('Configuración de ONECLICK incorrecta, revise los valores');
    }

    public function logOneclickPaymentIniciando(){
        $this->log->logInfo('B.1. Iniciando medio de pago Oneclick');
    }

    public function logOneclickPaymentAntesObtenerInscripcion($inscriptionId, $cartId, $amount){
        $this->log->logInfo('B.2. Antes de obtener inscripción de la BD => inscriptionId: '.$inscriptionId.', cartId: '.$cartId.', amount: '.$amount);
    }

    public function logOneclickPaymentDespuesObtenerInscripcion($inscriptionId, $ins){
        $this->log->logInfo('B.2. Despues de obtener inscripción de la BD => inscriptionId: '.$inscriptionId);
        $this->log->logInfo(json_encode($ins));
    }

    public function logOneclickPaymentAntesCrearTxBd($inscriptionId, $transaction){
        $this->log->logInfo('B.3. Preparando datos antes de crear la transacción en BD => inscriptionId: '.$inscriptionId);
        $this->log->logInfo(json_encode($transaction));
    }

    public function logOneclickPaymentCrearTxBdError($inscriptionId, $transaction){
        $this->log->logError('B.4. No se pudo crear la transacción en BD => inscriptionId: '.$inscriptionId);
        $this->log->logError(json_encode($transaction));
    }

    public function logOneclickPaymentAntesAutorizarTx($username, $tbkToken, $parentBuyOrder, $childBuyOrder, $amount){
        $this->log->logInfo('B.4. Preparando datos antes de autorizar la transacción en Transbank');
        $this->log->logInfo('username: '.$username.', tbkToken: '.$tbkToken.', parentBuyOrder: '.$parentBuyOrder.', childBuyOrder: '.$childBuyOrder.', amount: '.$amount);
    }

    public function logOneclickPaymentDespuesAutorizarTx($username, $tbkToken, $parentBuyOrder, $childBuyOrder, $amount, $result){
        $this->log->logInfo('B.5. Transacción con autorización en Transbank => username: '.$username.', tbkToken: '.$tbkToken.', parentBuyOrder: '.$parentBuyOrder.', childBuyOrder: '.$childBuyOrder.', amount: '.$amount);
        $this->log->logInfo(json_encode($result));
        if (!is_array($result) && $result->isApproved()){
            $this->log->logInfo('***** AUTORIZADO POR TBK OK *****');
            $this->log->logInfo('TRANSACCION VALIDADA POR TBK => username: '.$username.', tbkToken: '.$tbkToken.', parentBuyOrder: '.$parentBuyOrder.', childBuyOrder: '.$childBuyOrder.', amount: '.$amount);
            $this->log->logInfo('SI NO SE ENCUENTRA VALIDACION POR WooCommerce DEBE ANULARSE');
        }
    }

    public function logOneclickPaymentDespuesAutorizarTxError($parentBuyOrder, $childBuyOrder, $result){
        $this->log->logError('B.6. Transacción con autorización con error => parentBuyOrder: '.$parentBuyOrder.', childBuyOrder: '.$childBuyOrder);
        $this->log->logError(json_encode($result));
    }

    public function logOneclickPaymentDespuesAutorizarRechazadoTxError($parentBuyOrder, $childBuyOrder, $result){
        $this->log->logError('B.6. Transacción con autorización rechazada => parentBuyOrder: '.$parentBuyOrder.', childBuyOrder: '.$childBuyOrder);
        $this->log->logError(json_encode($result));
    }

    public function logOneclickPaymentDespuesValidateOrderWooCommerce($inscriptionId, $webpayTransaction){
        $this->log->logInfo('B.7. Procesando pago despues de validateOrder => inscriptionId: '.$inscriptionId);
        $this->log->logInfo(json_encode($webpayTransaction));
    }

    public function logOneclickPaymentTodoOk($inscriptionId, $webpayTransaction){
        $this->log->logInfo('***** TODO OK *****');
        $this->log->logInfo('TRANSACCION VALIDADA POR WooCommerce Y POR TBK EN ESTADO STATUS_APPROVED => INSCRIPTION_ID: '.$inscriptionId);
        $this->log->logInfo(json_encode($webpayTransaction));
    }
}