<?php

namespace Transbank\WooCommerce\WebpayRest\Helpers;

use Transbank\WooCommerce\WebpayRest\Utils\Template;

class NoticeHelper
{
    const NOTICE_REVIEW_ID = "tbk-review-notice";
    /**
     * display review notice if is necessary
     * @return void
     */
    public static function handleReviewNotice(): void
    {
        if (!self::shouldShowReviewNotice()) {
            return;
        }

        add_action(
            'admin_notices',
            function () {
                $tbkLogo = sprintf('%s%s', dirname(plugin_dir_url(__FILE__), 2), '/images/tbk-logo.png');
                $template = new Template();
                $template->render('public/notices/review-notice.php', [
                    'tbkLogo' => esc_url($tbkLogo)
                ]);
            }
        );
    }

    public static function dismissNoticesHandler()
    {
        $noticeId = $_POST['notice_id'] ?? '';

        if ($noticeId == self::NOTICE_REVIEW_ID) {
            $success = update_site_option('tbk_review_notice_showed', true);
        }
    }

    public static function registerNoticesDismissHook()
    {
        add_action('wp_ajax_dismiss_notice', NoticeHelper::class.'::dismissNoticesHandler');
    }

    /**
     * determines if the review notice should be displayed
     * @return boolean `true` when notice need to be showed, `false` otherwise
     */
    private static function shouldShowReviewNotice(): bool
    {
        $reviewNoticeShowed = get_site_option('tbk_review_notice_showed');
        if ($reviewNoticeShowed) {
            return false;
        }

        if (isset($_GET['page']) && isset($_GET['section'])) {
            return $_GET['page'] == 'wc-settings' && strstr($_GET['section'], 'transbank');
        }

        return false;
    }
}
