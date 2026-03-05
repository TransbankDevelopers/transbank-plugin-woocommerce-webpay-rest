<?php

namespace Transbank\WooCommerce\WebpayRest\Admin\Notices;

class MissingWooCommerceNotice implements NoticeInterface
{
    private NoticeRenderer $renderer;

    public function __construct(NoticeRenderer $renderer)
    {
        $this->renderer = $renderer;
    }

    public function shouldRender(): bool
    {
        if (class_exists('WC_Payment_Gateway')) {
            return false;
        }

        return current_user_can('install_plugins') || current_user_can('activate_plugins');
    }

    public function renderNotice(): void
    {
        $isWooInstalled = false;
        $isWooActivated = false;

        if (function_exists('get_plugins')) {
            $plugins = get_plugins();
            $isWooInstalled = !empty($plugins['woocommerce/woocommerce.php']);
        }

        if (function_exists('is_plugin_active')) {
            $isWooActivated = is_plugin_active('woocommerce/woocommerce.php');
        }

        $description = 'WooCommerce no se encuentra activo o no está instalado.';
        $actionButton = [
            'text' => 'Revisar WooCommerce',
            'action' => 'https://wordpress.org/plugins/woocommerce/',
        ];

        if (!$isWooInstalled && current_user_can('install_plugins')) {
            $description = 'WooCommerce no se encuentra instalado.';
            $actionButton = [
                'text' => 'Instalar WooCommerce',
                'action' => wp_nonce_url(
                    self_admin_url('update.php?action=install-plugin&plugin=woocommerce'),
                    'install-plugin_woocommerce'
                ),
            ];
        } elseif ($isWooInstalled && !$isWooActivated && current_user_can('activate_plugins')) {
            $description = 'WooCommerce no se encuentra activado.';
            $actionButton = [
                'text' => 'Activar WooCommerce',
                'action' => wp_nonce_url(
                    self_admin_url('plugins.php?action=activate&plugin=woocommerce/woocommerce.php&plugin_status=all'),
                    'activate-plugin_woocommerce/woocommerce.php'
                ),
            ];
        }

        $this->renderer->display([
            'id' => 'tbk-missing-woocommerce',
            'type' => 'error',
            'title' => $description,
            'description' => '',
            'isDismissible' => false,
            'actionButton' => $actionButton,
        ]);
    }
}
