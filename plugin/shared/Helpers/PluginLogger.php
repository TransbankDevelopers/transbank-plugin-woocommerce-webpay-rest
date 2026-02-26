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

    private static function getAllowedLogFilePaths(string $folderPath): array
    {
        $files = glob(trailingslashit($folderPath) . '*.log');
        if (!$files) {
            return [];
        }

        $allowed = [];
        foreach ($files as $filePath) {
            $allowed[basename($filePath)] = $filePath;
        }

        return $allowed;
    }

    public static function checkCanDownloadLogFile()
    {
        if (!is_user_logged_in()) {
            wp_send_json_error(['error' => 'Debes iniciar sesión para poder descargar']);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['error' => 'No tienes permisos para descargar']);
        }

        if (!check_ajax_referer('my-ajax-nonce', 'nonce', false)) {
            wp_send_json_error(['error' => 'Nonce inválido']);
        }

        $baseUploadDir = wp_upload_dir();
        $tbkLogsFolder = '/transbank_webpay_plus_rest/logs/';
        $logName = sanitize_text_field($_POST['file']);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        $folderPath = $baseUploadDir['basedir'] . $tbkLogsFolder;
        $allowedFiles = self::getAllowedLogFilePaths($folderPath);
        $filePath = $allowedFiles[$logName] ?? '';

        if ($filePath === '') {
            wp_send_json_error(['error' => 'No existe el archivo solicitado']);
        }

        $downloadUrl = admin_url(
            'admin-ajax.php?action=download_log_file&file=' .
                rawurlencode($logName) .
                '&nonce=' .
                rawurlencode($nonce)
        );
        wp_send_json_success(['downloadUrl' => $downloadUrl]);
    }

    public static function downloadLogFile()
    {
        if (!is_user_logged_in()) {
            wp_die('Debes iniciar sesión para poder descargar', 403);
        }

        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para descargar', 403);
        }

        if (!check_ajax_referer('my-ajax-nonce', 'nonce', false)) {
            wp_die('Nonce inválido', 403);
        }

        $baseUploadDir = wp_upload_dir();
        $tbkLogsFolder = '/transbank_webpay_plus_rest/logs/';
        $logName = isset($_GET['file']) ? sanitize_text_field($_GET['file']) : '';
        $safeFilename = rawurlencode(basename($logName));

        if ($logName === '') {
            wp_die('Archivo no especificado', 400);
        }

        $folderPath = $baseUploadDir['basedir'] . $tbkLogsFolder;
        $allowedFiles = self::getAllowedLogFilePaths($folderPath);
        $filePath = $allowedFiles[$safeFilename] ?? '';

        if ($filePath === '' || !is_readable($filePath)) {
            wp_die('No existe el archivo solicitado', 404);
        }

        if (ob_get_length()) {
            ob_end_clean();
        }

        nocache_headers();
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename*=UTF-8\'\'' . $safeFilename);
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        ExitHelper::terminate();
    }
}
