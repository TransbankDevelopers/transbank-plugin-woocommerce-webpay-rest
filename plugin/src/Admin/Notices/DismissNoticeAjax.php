<?php

namespace Transbank\WooCommerce\WebpayRest\Admin\Notices;

use Transbank\WooCommerce\WebpayRest\Config\TransbankPluginSettings;

class DismissNoticeAjax
{
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
        $noticeId = (string) ($_POST['notice_id'] ?? '');

        if ($noticeId === ReviewNotice::NOTICE_ID) {
            $this->settings->setReviewNoticeDismissed(true);
        }

        wp_send_json_success();
    }
}
