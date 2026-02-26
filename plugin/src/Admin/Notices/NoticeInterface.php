<?php

namespace Transbank\WooCommerce\WebpayRest\Admin\Notices;

interface NoticeInterface
{
    public function shouldRender(): bool;

    public function renderNotice(): void;
}
