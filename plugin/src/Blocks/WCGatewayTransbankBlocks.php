<?php

namespace Transbank\WooCommerce\WebpayRest\Blocks;
use Automattic\WooCommerce\Blocks\Payments\PaymentResult;
use Automattic\WooCommerce\Blocks\Payments\PaymentContext;

trait WCGatewayTransbankBlocks
{
    private $gateway;
    private $paymentId;

    private $productName;

    private $scriptInfo;
    public function initialize() {
        $this->settings = get_option( $this->paymentId . '_settings', []);
        $this->gateway = $this->get_gateway();
        add_action( 'woocommerce_rest_checkout_process_payment_with_context', [$this, 'processErrorPayment'], 10, 2 );
    }

    public function get_payment_method_script_handles() {
        wp_register_script(
            'wc_transbank_'. $this->productName .'_payment',
            dirname(dirname(plugins_url('/', __FILE__))) . '/js/front/'. $this->productName .'_blocks.js',
            $this->scriptInfo['dependencies'],
            $this->scriptInfo['version'],
            true
        );

        return['wc_transbank_'. $this->productName .'_payment'];
    }

    public function get_payment_method_data() {
        return [
            'title' => $this->gateway->title,
            'description' => $this->gateway->description,
            'supports' => $this->gateway->supports,
            'icon' => $this->gateway->icon,
            'id' => $this->gateway->id
        ];
    }

    private function get_gateway() {
        $gateways = WC()->payment_gateways->get_available_payment_gateways();
        if(isset($gateways[$this->paymentId])) {
            return $gateways[$this->paymentId];
        }
        return null;
    }

    public function processErrorPayment(PaymentContext $context, PaymentResult &$result) {
        add_action(
            'wc_gateway_transbank_process_payment_error_' . $this->paymentId,
            function( $error, $shouldThrowError = false ) use ( &$result ) {
                $payment_details                 = $result->payment_details;
                $payment_details['errorMessage'] = wp_strip_all_tags( $error->getMessage() );
                $result->set_payment_details( $payment_details );
                $result->set_status('failure');
                if ($shouldThrowError) {
                    throw $error;
                }
            }, 10, 2
        );
    }

    public function is_active() {
        if(isset($this->gateway)) {
            return $this->gateway->enabled == 'yes';
        }
        return false;
    }
}
