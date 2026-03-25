<?php

namespace Transbank\WooCommerce\WebpayRest\Admin\Notices;

use Transbank\WooCommerce\WebpayRest\Config\TransbankPluginSettings;

class DismissNoticeAjax
{
    private const REQUIRED_CAPABILITY = 'manage_woocommerce';

    private TransbankPluginSettings $settings;

    public function __construct(TransbankPluginSettings $settings)
    {
        $this->settings = $settings;
    }

    public function register(): void
    {
        add_action('wp_ajax_dismiss_notice', [$this, 'handle']);
    }

    public function handle(): void
    {
        if (!current_user_can(self::REQUIRED_CAPABILITY)) {
            wp_send_json_error(['error' => 'No tienes permisos para realizar esta acción'], 403);
        }

        if (!check_ajax_referer('my-ajax-nonce', 'nonce', false)) {
            wp_send_json_error(['error' => 'Nonce inválido'], 403);
        }

        $noticeId = (string) ($_POST['notice_id'] ?? '');

        if ($noticeId === ReviewNotice::NOTICE_ID) {
            $this->settings->setReviewNoticeDismissed(true);
        }

        wp_send_json_success();
    }
}
