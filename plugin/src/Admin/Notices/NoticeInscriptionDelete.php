<?php

namespace Transbank\WooCommerce\WebpayRest\Admin\Notices;

class NoticeInscriptionDelete implements NoticeInterface
{
    private NoticeRenderer $renderer;

    public function __construct(NoticeRenderer $renderer)
    {
        $this->renderer = $renderer;
    }

    public function shouldRender(): bool
    {
        $page = (string) ($_GET['page'] ?? '');
        $section = (string) ($_GET['section'] ?? '');

        if ($page !== 'transbank_webpay_plus_rest' && $section !== 'transbank_webpay_plus_rest') {
            return false;
        }

        $type = (string) ($_GET['tbk_notice_type'] ?? '');
        $msg = (string) ($_GET['tbk_notice_msg'] ?? '');

        return $type !== '' && $msg !== '';
    }

    public function renderNotice(): void
    {
        $type = (string) ($_GET['tbk_notice_type'] ?? '');
        $msg = (string) ($_GET['tbk_notice_msg'] ?? '');
        $id = (string) ($_GET['tbk_notice_id'] ?? 'tbk-inscription-delete-notice');

        $this->renderer->display([
            'id' => $id !== '' ? $id : 'tbk-inscription-delete-notice',
            'type' => $type,
            'title' => $msg,
            'isDismissible' => true,
        ]);
    }
}
