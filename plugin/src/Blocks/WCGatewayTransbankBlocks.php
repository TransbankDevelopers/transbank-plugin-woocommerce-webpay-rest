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
    private $frontStyleHandle;

    public function initialize()
    {
        $this->settings = get_option($this->paymentId . '_settings', []);
        $this->gateway = $this->getGateway();
        $this->frontStyleHandle = $this->registerFrontStyleHandle();
        add_action(
            'woocommerce_rest_checkout_process_payment_with_context',
            [$this, 'processErrorPayment'],
            $this->getProcessErrorHookPriority(),
            $this->getProcessErrorHookAcceptedArgs()
        );
    }

    public function get_payment_method_script_handles()
    {
        $entryBaseName = $this->getFrontEntryBaseName();

        wp_register_script(
            'wc_transbank_' . $this->productName . '_payment',
            $this->getFrontAssetUrl($entryBaseName . '.js'),
            $this->scriptInfo['dependencies'],
            $this->scriptInfo['version'],
            true
        );

        if ($this->frontStyleHandle !== null) {
            wp_enqueue_style($this->frontStyleHandle);
        }

        return ['wc_transbank_' . $this->productName . '_payment'];
    }

    public function get_payment_method_data()
    {
        return [
            'title' => $this->gateway->title,
            'description' => $this->gateway->description,
            'supports' => $this->gateway->supports,
            'icon' => $this->gateway->icon,
            'id' => $this->gateway->id
        ];
    }

    private function getGateway()
    {
        $gateways = WC()->payment_gateways->get_available_payment_gateways();
        if (isset($gateways[$this->paymentId])) {
            return $gateways[$this->paymentId];
        }
        return null;
    }

    protected function getFrontAssetBuildPath(): string
    {
        return dirname(dirname(plugin_dir_path(__FILE__))) . $this->getFrontAssetsBuildDir();
    }

    protected function getFrontAssetUrl(string $fileName): string
    {
        return dirname(dirname(plugins_url('/', __FILE__))) . $this->getFrontAssetsBuildDir() . ltrim($fileName, '/');
    }

    protected function getFrontEntryBaseName(): string
    {
        return 'front-checkout-' . $this->productName;
    }

    protected function getFrontStyleEntryBaseName(): string
    {
        return $this->getFrontEntryBaseName() . '-style';
    }

    protected function getFrontStyleHandle(): string
    {
        return 'wc_transbank_' . $this->productName . '_payment_style';
    }

    protected function registerFrontStyleHandle(): ?string
    {
        $entryBaseName = $this->getFrontStyleEntryBaseName();
        $cssFilePath = $this->getFrontAssetBuildPath() . $entryBaseName . '.css';

        if (!is_readable($cssFilePath)) {
            return null;
        }

        $styleHandle = $this->getFrontStyleHandle();

        wp_register_style(
            $styleHandle,
            $this->getFrontAssetUrl($entryBaseName . '.css'),
            [],
            tbkSafeFilemtime($cssFilePath)
        );

        return $styleHandle;
    }

    protected function getFrontAssetsBuildDir(): string
    {
        return '/assets/build/front/';
    }

    protected function getProcessErrorHookPriority(): int
    {
        return 10;
    }

    protected function getProcessErrorHookAcceptedArgs(): int
    {
        return 2;
    }

    public function processErrorPayment(PaymentContext $context, PaymentResult &$result)
    {
        if ($context->payment_method !== $this->paymentId) {
            return;
        }

        add_action(
            'wc_gateway_transbank_process_payment_error_' . $this->paymentId,
            function ($error, $shouldThrowError = false) use (&$result) {
                $payment_details = $result->payment_details;
                $payment_details['errorMessage'] = wp_strip_all_tags($error->getMessage());
                $result->set_payment_details($payment_details);
                $result->set_status('failure');
                if ($shouldThrowError) {
                    throw $error;
                }
            },
            $this->getProcessErrorHookPriority(),
            $this->getProcessErrorHookAcceptedArgs()
        );
    }

    public function is_active()
    {
        if (isset($this->gateway)) {
            return $this->gateway->enabled == 'yes';
        }
        return false;
    }
}
