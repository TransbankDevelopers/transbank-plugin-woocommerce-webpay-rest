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
        $logFile = basename($summary['last']);

        if (isset($_GET['log_file'])) {
            $isLogFileNameValid = $this->validateLogFileName($_GET['log_file'], $summary['logs']);

            if ($isLogFileNameValid) {
                $logFile = $_GET['log_file'];
            }
        }

        $logDetail = $this->log->getLogDetail($logFile);
        $folderHasLogs = $summary['length'] > 0;

        $this->template->render('admin/log.php', [
            'resume' => $summary,
            'lastLog' => $logDetail,
            'folderHasLogs' => $folderHasLogs
        ]);
    }

    private function validateLogFileName(String $logFileName, array $logFiles): bool
    {
        foreach ($logFiles as $logData) {
            if (in_array($logFileName, $logData)) {
                return true;
            }
        }
        return false;
    }
}
