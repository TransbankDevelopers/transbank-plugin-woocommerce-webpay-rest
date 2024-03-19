<?php

namespace Transbank\WooCommerce\WebpayRest\Controllers;

use Transbank\Plugin\Helpers\PluginLogger;
use Transbank\WooCommerce\WebpayRest\Utils\Template;
use Transbank\WooCommerce\WebpayRest\Helpers\TbkFactory;

class LogController
{

    private Template $template;
    private PluginLogger $log;

    public function __construct()
    {
        $this->template = new Template();
        $this->log = TbkFactory::createLogger();
    }
    public function show()
    {
        $summary = $this->log->getInfo();
        $lastLog = $this->log->getLogDetail(basename($summary['last']));
        $folderHasLogs = $summary['length'] > 0;

        $this->template->render('admin/log.php', [
            'resume' => $summary,
            'lastLog' => $lastLog,
            'folderHasLogs' => $folderHasLogs
        ]);
    }
}
