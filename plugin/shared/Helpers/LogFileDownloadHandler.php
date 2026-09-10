<?php

namespace Transbank\Plugin\Helpers;

final class LogFileDownloadHandler
{
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
}
