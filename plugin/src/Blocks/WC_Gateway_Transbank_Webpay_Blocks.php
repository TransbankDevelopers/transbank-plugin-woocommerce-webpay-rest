<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Transbank\WooCommerce\WebpayRest\PaymentGateways\WC_Gateway_Transbank_Webpay_Plus_REST;

final class WCGatewayTransbankWebpayBlocks extends AbstractPaymentMethodType {
    private $gateway;
    protected $name = 'transbank_webpay_plus_rest';

    public function initialize() {
        $this->settings = get_option('transbank_webpay_plus_rest_settings', []);
        $this->gateway = new WC_Gateway_Transbank_Webpay_Plus_REST();
    }

    public function is_active() {
        return $this->gateway->is_available();
    }

    public function get_payment_method_script_handles() {
        $scriptPath = dirname(dirname(plugin_dir_path(__FILE__))) . '/js/front/';
        $scriptInfo = require_once $scriptPath . 'webpay_blocks.asset.php';
        wp_register_script(
            'wc_transbank_webpay_payment',
            dirname(dirname(plugins_url('/', __FILE__))) . '/js/front/webpay_blocks.js',
            $scriptInfo['dependencies'],
            $scriptInfo['version'],
            true
        );

        return['wc_transbank_webpay_payment'];
    }

    public function get_payment_method_data() {
		return [
			'title' => $this->gateway->title,
			'description' => $this->gateway->description,
			'supports' => $this->gateway->supports,
            'icon' => $this->gateway->icon
		];
	}
}
