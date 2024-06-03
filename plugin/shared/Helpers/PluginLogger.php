<?php

namespace Transbank\Plugin\Helpers;

use DateTimeZone;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;
use Transbank\Plugin\Model\LogConfig;

final class PluginLogger implements ILogger
{

    const CACHE_LOG_NAME = 'transbank_log_file_name';

    private $logger;
    private $config;

    /**
     * Este constructor inicializa el log segun la configuración alcanzada
     * Toma como valores por defecto lo siguiente:
     * date format : "Y-m-d H:i:s" "Y n j, g:i a"
     * output format : "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n"
     * @param Throwable $e
     */
    public function __construct(LogConfig $config)
    {
        $this->config = $config;

        $logFilePath = $this->getLogFilePath();
        $this->initializeLogger($logFilePath);
    }

    private function initializeLogger(string $logFilePath)
    {
        $ecommerceTz = new DateTimeZone(wc_timezone_string());
        $dateFormat = "Y-m-d H:i:s P";
        $output = "%datetime% > %level_name% > %message% %context% %extra%\n";
        $formatter = new LineFormatter($output, $dateFormat);

        $stream = new RotatingFileHandler(
            $logFilePath,
            100,
            Logger::DEBUG
        );
        $stream->setFormatter($formatter);

        $this->logger = new Logger('transbank');
        $this->logger->setTimezone($ecommerceTz);
        $this->logger->pushHandler($stream);
    }

    private function getLogFilePath(): string
    {
        $logFileName = $this->getLogFileNameFromCache();

        if (!$logFileName) {
            $logFileName = $this->getLogFileName();
            $expireTime = strtotime('tomorrow') - time();
            $this->saveLogFileNameInCache($logFileName, $expireTime);
        }

        $logDir = $this->getLogDir();
        return $logDir . $logFileName;
    }

    public function getLogger()
    {
        return $this->logger;
    }

    public function getConfig()
    {
        return $this->config;
    }

    public function logDebug($msg)
    {
        $this->logger->debug($msg);
    }

    public function logInfo($msg)
    {
        $this->logger->info($msg);
    }

    public function logError($msg)
    {
        $this->logger->error($msg);
    }

    public function getInfo()
    {
        $files = glob($this->config->getLogDir() . '/*.log');
        if (!$files) {
            return [
                'dir'      => $this->config->getLogDir(),
                'length'   => 0,
                'logs'     => [],
                'last'     => ''
            ];
        }
        $files = array_combine($files, array_map('filemtime', $files));
        arsort($files);

        $logs = [];
        foreach ($files as $key => $value) {
            $logs[] = [
                "filename" => basename($key),
                "modified" => $value
            ];
        }

        return [
            'dir'      => $this->config->getLogDir(),
            'last'     => key($files),
            'logs'     => $logs,
            'length'   => count($logs)
        ];
    }

    public function getLogDetail($filename, $replaceNewline = false)
    {
        if ($filename == '') {
            return [];
        }
        $fle = $this->config->getLogDir() . '/' . $filename;
        $content = file_get_contents($fle);
        if ($replaceNewline && $content !== false) {
            $content = str_replace("\n", '#@#', $content);
        }
        return [
            'filename'  => $fle,
            'content'   => $content,
            'size'      => $this->formatBytes($fle),
            'lines'    => count(file($fle)),
        ];
    }

    private function formatBytes($path)
    {
        $bytes = sprintf('%u', filesize($path));
        if ($bytes > 0) {
            $unit = intval(log($bytes, 1024));
            $units = ['B', 'KB', 'MB', 'GB'];
            if (array_key_exists($unit, $units) === true) {
                return sprintf('%d %s', $bytes / pow(1024, $unit), $units[$unit]);
            }
        }
        return $bytes;
    }

    private function getLogDir(): string
    {
        $logDir = $this->config->getLogDir();
        return trailingslashit($logDir);
    }

    private function getLogFileName(): string
    {
        $uniqueId = uniqid('', true);
        return 'log_transbank_' . $uniqueId . '.log';
    }

    private function getLogFileNameFromCache()
    {
        return get_transient(self::CACHE_LOG_NAME);
    }

    private function saveLogFileNameInCache(string $logFileName, int $expireTime)
    {
        set_transient(self::CACHE_LOG_NAME, $logFileName, $expireTime);
    }
}
