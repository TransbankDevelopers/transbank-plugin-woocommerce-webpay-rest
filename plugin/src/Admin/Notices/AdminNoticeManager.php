<?php

namespace Transbank\WooCommerce\WebpayRest\Admin\Notices;

class AdminNoticeManager
{
    private array $notices;

    public function __construct(NoticeInterface ...$notices)
    {
        $this->notices = $notices;
    }

    public function register(): void
    {
        add_action('admin_notices', [$this, 'renderAll']);
    }

    public function renderAll(): void
    {
        foreach ($this->notices as $notice) {
            if ($notice->shouldRender()) {
                $notice->renderNotice();
            }
        }
    }
}
