<?php

namespace Transbank\WooCommerce\WebpayRest\Admin\Notices;

use Transbank\WooCommerce\WebpayRest\Utils\Template;

class NoticeRenderer
{
    private string $pluginFile;

    public function __construct(string $pluginFile)
    {
        $this->pluginFile = $pluginFile;
    }

    public function display(array $data): void
    {
        $defaults = [
            'id' => 'message',
            'type' => 'error',
            'title' => '',
            'titleClass' => '',
            'description' => '',
            'logoUrl' => plugin_dir_url($this->pluginFile) . 'images/tbk-logo.png',
            'logoAlt' => 'Transbank Logo',
            'logoHeight' => 50,
            'isDismissible' => true,
            'actionButton' => [],
        ];

        (new Template())->render('admin/components/notice.php', array_merge($defaults, $data));
    }
}
