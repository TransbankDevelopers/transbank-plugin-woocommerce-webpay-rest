<?php

namespace Transbank\WooCommerce\WebpayRest\Controllers;

use \Exception;
use Transbank\Webpay\Oneclick\MallInscription;
use Transbank\WooCommerce\WebpayRest\Helpers\LogHandler;
use Transbank\WooCommerce\WebpayRest\Helpers\OneclickUtil;
use Transbank\WooCommerce\WebpayRest\Models\Inscription;
use  Transbank\WooCommerce\WebpayRest\Exceptions\Oneclick\UserCancelInscriptionOneclickException;
use  Transbank\WooCommerce\WebpayRest\Exceptions\Oneclick\InvalidStatusInscriptionOneclickException;
use  Transbank\WooCommerce\WebpayRest\Exceptions\Oneclick\TimeoutInscriptionOneclickException;
use  Transbank\WooCommerce\WebpayRest\Exceptions\Oneclick\WithoutTokenInscriptionOneclickException;
use  Transbank\WooCommerce\WebpayRest\Exceptions\Oneclick\FinishInscriptionOneclickException;
use  Transbank\WooCommerce\WebpayRest\Exceptions\Oneclick\RejectedInscriptionOneclickException;
use  Transbank\WooCommerce\WebpayRest\Exceptions\Oneclick\GetInscriptionOneclickException;
use WC_Payment_Token_Oneclick;
use WC_Payment_Tokens;

class OneclickInscriptionResponseController
{
    protected $logger;
    protected $oneclickInscription;
    protected $gatewayId;

    /**
     * OneclickInscriptionResponseController constructor.
     */
    public function __construct(MallInscription $oneclickInscription, $gatewayId, $logger = null)
    {
        $this->logger = $logger ?? new LogHandler();
        $this->oneclickInscription = $oneclickInscription;
        $this->gatewayId = $gatewayId;
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
     * @throws \Transbank\WooCommerce\WebpayRest\Exceptions\TokenNotFoundOnDatabaseException
     */
    public function response()
    {
        $order = null;
        try {
            $resp = OneclickUtil::processTbkReturnAndFinishInscription($_SERVER, $_GET, $_POST);
            $inscription = $resp['inscription'];
            $finishInscriptionResponse = $resp['finishInscriptionResponse'];
            $order = $this->getWcOrder($inscription->order_id);
            $from = $inscription->from;
            do_action('transbank_oneclick_inscription_finished', $order, $from);

            // Todo: guardar la información del usuario al momento de crear la inscripción y luego obtenerla en base al token,
            // por si se pierde la sesión
            $userInfo = wp_get_current_user();
            if (!$userInfo) {
                $this->logger->logError('You were logged out');
            }

            wc_add_notice(__('La tarjeta ha sido inscrita satisfactoriamente. Aún no se realiza ningún cobro. Ahora puedes realizar el pago.', 'transbank_wc_plugin'), 'success');
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

            do_action('transbank_oneclick_inscription_approved', $finishInscriptionResponse, $token, $from);
            $this->logger->logInfo('Inscription finished successfully for user #'.$inscription->user_id);
            $this->redirectUser($from);

        } catch (TimeoutInscriptionOneclickException $e) {
            wc_add_notice($e->getMessage(), 'error');
            wp_redirect(wc_get_checkout_url());
            exit;
        }  catch (WithoutTokenInscriptionOneclickException $e) {
            exit;
        } catch (GetInscriptionOneclickException $e) {
            wc_add_notice($e->getMessage(), 'error');
            throw $e;
        } catch (UserCancelInscriptionOneclickException $e) {
            wc_add_notice($e->getMessage(), 'warning');
            $inscription = $e->getInscription();
            if ($inscription != null) {
                $order = $this->getWcOrder($inscription->order_id);
            }
            if ($order != null) {
                $order->add_order_note('El usuario canceló la inscripción en el formulario de pago');
                $params = ['transbank_cancelled_order' => 1];
                $redirectUrl = add_query_arg($params, wc_get_checkout_url());
                wp_safe_redirect($redirectUrl);
                exit;
            }
            $this->redirectUser($inscription->from);
            exit;
        }catch (InvalidStatusInscriptionOneclickException $e) {
            $inscription = $e->getInscription();
            $this->redirectUser($inscription->from);
        } catch (FinishInscriptionOneclickException $e) {
            wc_add_notice($e->getMessage(), 'error');
            $inscription = $e->getInscription();
            $this->redirectUser($inscription->from);
            exit;
        }  catch (RejectedInscriptionOneclickException $e) {
            wc_add_notice($e->getMessage(), 'error');
            $inscription = $e->getInscription();
            $this->redirectUser($inscription->from);
            exit;
        } catch (Exception $e) {
            throw $e;
        }
    }


    /**
     * @param $from
     */
    public function redirectUser($from)
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
            wp_redirect($redirectUrl);
        }

        exit();
    }
}
