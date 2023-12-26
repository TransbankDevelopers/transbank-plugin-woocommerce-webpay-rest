<?php

namespace Transbank\WooCommerce\WebpayRest\Blocks;

trait WCGatewayTransbankBlocks
{
    private $gateway;
    private $paymentId;

    private $productName;

    private $scriptInfo;
    public function initialize() {
        $this->settings = get_option( $this->paymentId . '_settings', []);
        $this->gateway = $this->get_gateway();
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
            'icon' => $this->gateway->icon
		];
	}

    private function get_gateway() {
        $gateways = WC()->payment_gateways->get_available_payment_gateways();
        if(isset($gateways[$this->paymentId])) {
            return $gateways[$this->paymentId];
        }
        return null;
    }
}
