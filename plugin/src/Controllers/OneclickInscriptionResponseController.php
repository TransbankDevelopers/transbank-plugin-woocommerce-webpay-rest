<?php

namespace Transbank\WooCommerce\WebpayRest\Controllers;

use \Exception;
use Transbank\WooCommerce\WebpayRest\Helpers\TbkFactory;
use Transbank\WooCommerce\WebpayRest\Helpers\BlocksHelper;
use Transbank\WooCommerce\WebpayRest\Models\Inscription;
use  Transbank\Plugin\Exceptions\Oneclick\UserCancelInscriptionOneclickException;
use  Transbank\Plugin\Exceptions\Oneclick\InvalidStatusInscriptionOneclickException;
use  Transbank\Plugin\Exceptions\Oneclick\TimeoutInscriptionOneclickException;
use  Transbank\Plugin\Exceptions\Oneclick\WithoutTokenInscriptionOneclickException;
use  Transbank\Plugin\Exceptions\Oneclick\FinishInscriptionOneclickException;
use  Transbank\Plugin\Exceptions\Oneclick\RejectedInscriptionOneclickException;
use  Transbank\Plugin\Exceptions\Oneclick\GetInscriptionOneclickException;
use Transbank\WooCommerce\WebpayRest\Tokenization\WC_Payment_Token_Oneclick;
use WC_Payment_Tokens;

class OneclickInscriptionResponseController
{
    protected $logger;
    protected $gatewayId;
    /**
    * @var Transbank\WooCommerce\WebpayRest\OneclickTransbankSdk
    */
    protected $oneclickTransbankSdk;
    /**
     * OneclickInscriptionResponseController constructor.
     */
    public function __construct($gatewayId)
    {
        $this->logger = TbkFactory::createLogger();
        $this->gatewayId = $gatewayId;
        $this->oneclickTransbankSdk = TbkFactory::createOneclickTransbankSdk();
    }

    private function getWcOrder($orderId) {
        if ($orderId == null){
            return null;
        }
        return new \WC_Order($orderId);
    }

    private function savePaymentToken($inscription, $finishInscriptionResponse){
        $token = new WC_Payment_Token_Oneclick();
        $token->set_token($finishInscriptionResponse->getTbkUser()); // Token comes from payment processor
        $token->set_gateway_id($this->gatewayId);
        $token->set_last4(substr($finishInscriptionResponse->getCardNumber(), -4));
        $token->set_email($inscription->email);
        $token->set_username($inscription->username);
        $token->set_card_type($finishInscriptionResponse->getCardType());
        $token->set_user_id($inscription->user_id);
        $token->set_environment($inscription->environment);
        // Save the new token to the database
        $token->save();
        return $token;
    }

    /**
     * @throws \Transbank\Plugin\Exceptions\TokenNotFoundOnDatabaseException
     */
    public function response()
    {
        $order = null;
        try {
            $resp = $this->oneclickTransbankSdk->processTbkReturnAndFinishInscription($_SERVER, $_GET, $_POST);
            $inscription = $resp['inscription'];
            $finishInscriptionResponse = $resp['finishInscriptionResponse'];
            $order = $this->getWcOrder($inscription->order_id);
            $from = $inscription->from;
            do_action('wc_transbank_oneclick_inscription_finished', [
                'order' => $order->get_data(),
                'from' => $from
            ]);

            // Todo: guardar la información del usuario al momento de crear la inscripción y luego obtenerla en base al token,
            // por si se pierde la sesión
            $userInfo = wp_get_current_user();
            if (!$userInfo) {
                $this->logger->logError('You were logged out');
            }
            $message = 'Tarjeta inscrita satisfactoriamente. Aún no se realiza ningún cobro. Ahora puedes realizar el pago.';
            BlocksHelper::addLegacyNotices(__($message, 'transbank_wc_plugin'), 'success');
            $this->logger->logInfo('[ONECLICK] Inscripción aprobada');
            $token = $this->savePaymentToken($inscription, $finishInscriptionResponse);
            if ($order) {
                $order->add_order_note('Tarjeta inscrita satisfactoriamente');
            }

            Inscription::update($inscription->id, [
                'token_id' => $token->get_id(),
            ]);

            // Set this token as the users new default token
            WC_Payment_Tokens::set_users_default(get_current_user_id(), $token->get_id());

            do_action('wc_transbank_oneclick_inscription_approved', [
                'transbankInscriptionResponse' => $finishInscriptionResponse,
                'transbankToken' => $token,
                'from' =>$from
            ]);
            $this->logger->logInfo('Inscription finished successfully for user #'.$inscription->user_id);
            $this->redirectUser($from, BlocksHelper::ONECLICK_SUCCESSFULL_INSCRIPTION);

        } catch (TimeoutInscriptionOneclickException $e) {
            BlocksHelper::addLegacyNotices($e->getMessage(), 'error');
            $params = ['transbank_status' => BlocksHelper::ONECLICK_TIMEOUT];
            $redirectUrl = add_query_arg($params, wc_get_checkout_url());
            wp_redirect($redirectUrl);
        }  catch (WithoutTokenInscriptionOneclickException $e) {
            $params = ['transbank_status' => BlocksHelper::ONECLICK_WITHOUT_TOKEN];
            BlocksHelper::addLegacyNotices($e->getMessage(), 'error');
            $redirectUrl = add_query_arg($params, wc_get_checkout_url());
            wp_safe_redirect($redirectUrl);
        } catch (GetInscriptionOneclickException $e) {
            BlocksHelper::addLegacyNotices($e->getMessage(), 'error');
            throw $e;
        } catch (UserCancelInscriptionOneclickException $e) {
            BlocksHelper::addLegacyNotices($e->getMessage(), 'warning');
            $inscription = $e->getInscription();
            if ($inscription != null) {
                $order = $this->getWcOrder($inscription->order_id);
            }
            if ($order != null) {
                $order->add_order_note('El usuario canceló la inscripción en el formulario de pago');
                $params = ['transbank_cancelled_order' => 1,
                        'transbank_status' => BlocksHelper::ONECLICK_USER_CANCELED];
                $redirectUrl = add_query_arg($params, wc_get_checkout_url());
                wp_safe_redirect($redirectUrl);
            }
            $this->redirectUser($inscription->from, BlocksHelper::ONECLICK_USER_CANCELED);
        }catch (InvalidStatusInscriptionOneclickException $e) {
            $inscription = $e->getInscription();
            $this->redirectUser($inscription->from, BlocksHelper::ONECLICK_INVALID_STATUS);
        } catch (FinishInscriptionOneclickException $e) {
            BlocksHelper::addLegacyNotices($e->getMessage(), 'error');
            $inscription = $e->getInscription();
            $this->redirectUser($inscription->from, BlocksHelper::ONECLICK_FINISH_ERROR);
        }  catch (RejectedInscriptionOneclickException $e) {
            BlocksHelper::addLegacyNotices($e->getMessage(), 'error');
            $inscription = $e->getInscription();
            $this->redirectUser($inscription->from, BlocksHelper::ONECLICK_REJECTED_INSCRIPTION);
        } catch (Exception $e) {
            throw $e;
        }
    }


    /**
     * @param $from
     */
    public function redirectUser($from, $errorCode = null)
    {
        $redirectUrl = null;
        if ($from === 'checkout') {
            $checkoutPageId = wc_get_page_id('checkout');
            $redirectUrl = $checkoutPageId ? get_permalink($checkoutPageId) : null;
        }
        if ($from === 'my_account') {
            $redirectUrl = get_permalink(get_option('woocommerce_myaccount_page_id')).'/'.get_option(
                'woocommerce_myaccount_payment_methods_endpoint',
                'payment-methods'
            );
        }
        if ($redirectUrl) {
            if (isset($errorCode)) {
                $params = ['transbank_status' => $errorCode];
                $redirectUrl = add_query_arg($params, $redirectUrl);
            }
            wp_redirect($redirectUrl);
        }
    }
}
