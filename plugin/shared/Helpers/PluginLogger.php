<?php

namespace Transbank\Plugin\Helpers;

use DateTimeZone;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;
use Transbank\Plugin\Model\LogConfig;

final class PluginLogger
{
    const CACHE_LOG_NAME = 'transbank_log_file_name';
    private const MAX_LOG_DEPTH = 10;

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
        $formatter = new LineFormatter($output, $dateFormat, true, true);

        $stream = new RotatingFileHandler(
            $logFilePath,
            100,
            Logger::DEBUG
        );
        $stream->setFormatter($formatter);

        $this->logger = new Logger('transbank');
        $this->logger->setTimezone($ecommerceTz);
        $this->logger->pushHandler($stream);

        $masker = new MaskData($this->config->isMaskingEnabled());
        $this->logger->pushProcessor(new LoggerMaskProcessor($masker));
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

    public function logDebug(string $msg, array $context = [])
    {
        $this->logger->debug($msg, $context);
    }

    public function logInfo(string $msg, array $context = [])
    {
        $this->logger->info($msg, $context);
    }

    public function logError(string $msg, array $context = [])
    {
        $this->logger->error($msg, $context);
    }

    public function logWarning(string $msg, array $context = [])
    {
        $this->logger->warning($msg, $context);
    }

    public static function sanitizeContextForLogs(array $context): array
    {
        $sanitizedContext = [];

        foreach ($context as $key => $value) {
            $sanitizedContext[$key] = self::sanitizeLogValue($value);
        }

        return $sanitizedContext;
    }

    private static function sanitizeLogValue(mixed $value, int $depth = 0): mixed
    {
        if ($depth > self::MAX_LOG_DEPTH) {
            return '[array too deep]';
        }

        $sanitizedValue = null;

        if (is_array($value)) {
            $sanitizedValue = array_map(function ($nestedValue) use ($depth) {
                return self::sanitizeLogValue($nestedValue, $depth + 1);
            }, $value);
        } elseif (is_null($value) || is_bool($value) || is_int($value) || is_float($value)) {
            $sanitizedValue = $value;
        } else {
            $sanitizedValue = sanitize_text_field(wp_strip_all_tags((string) $value));
        }

        return $sanitizedValue;
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
