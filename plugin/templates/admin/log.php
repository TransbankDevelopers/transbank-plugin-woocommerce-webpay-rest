<?php
if (!defined('ABSPATH')) {
    return;
}
$logDirectoryTitle = "Carpeta en el servidor en donde se guardan los archivos" .
    " con la información de cada compra mediante Webpay";
$lineCountTitle = "Cantidad de líneas que posee el último archivo de registro creado";
$selectedFilename = isset($lastLog['filename']) ? basename((string) $lastLog['filename']) : '';
?>

<div class="tbk-box" id="logs-container">
    <h3 class="tbk_title_h3">Información de Registros</h3>
    <div class="tbk-plugin-info-container">
        <div class="info-column">
            <div title="<?= esc_attr($logDirectoryTitle) ?>" class="label label-info">?</div>
        </div>
        <div class="info-column">
            <span class="highlight-text"> Directorio de registros: </span>
        </div>
        <div class="info-column">
            <span class="label">
                <?= esc_html((string) ($resume['dir'] ?? '-')) ?>
            </span>
        </div>
    </div>
    <div class="tbk-plugin-info-container">
        <div class="info-column">
            <div title="Lista de archivos logs disponibles" class="label label-info">?
            </div>
        </div>
        <div class="info-column">
            <span class="highlight-text"> Lista de logs: </span>
        </div>
        <div class="info-column">
            <form action="/wp-admin/admin.php" method="get">
                <input type="hidden" name="page" value="transbank_webpay_plus_rest">
                <input type="hidden" name="tbk_tab" value="logs">

                <select class="select label" name="log_file" id="log_file" <?= !$folderHasLogs ? 'disabled' : '' ?>>
                    <?php
                    if (!$folderHasLogs) {
                        ?>
                        <option value="" selected>No hay archivos log</option>
                        <?php
                    }

                    foreach ($resume['logs'] as $index) {
                        $filename = (string) ($index['filename'] ?? '');
                        ?>
                        <option value="<?= esc_attr($filename) ?>" <?= selected($filename, $selectedFilename, false) ?>>
                            <?= esc_html($filename) ?>
                        </option>
                        <?php
                    }
                    ?>
                </select>
                <input type="submit" class="button button-primary tbk-button-primary"
                    <?= !$folderHasLogs ? 'disabled' : '' ?>
                    value="Ver">
                <button class="button button-secondary tbk-button-secondary" id="btnDownload">Descargar</button>
            </form>
        </div>
    </div>

    <h3 class="tbk_title_h3">Información del archivo</h3>
    <div id="tbk-last-logs">
        <div class="tbk-plugin-info-container">
            <div class="info-column">
                <div title="Peso del último archivo de registro creado" class="label label-info">?
                </div>
            </div>
            <div class="info-column-plugin">
                <span class="highlight-text"> Peso del Documento: </span>
            </div>
            <div class="info-column-plugin">
                <span class="label">
                    <?= esc_html((string) ($lastLog['size'] ?? '-')) ?>
                </span>
            </div>
        </div>
        <div class="tbk-plugin-info-container">
            <div class="info-column">
                <div title="<?= esc_attr($lineCountTitle) ?>" class="label label-info">?</div>
            </div>
            <div class="info-column-plugin">
                <span class="highlight-text"> Cantidad de Líneas: </span>
            </div>
            <div class="info-column-plugin">
                <span class="label">
                    <?= esc_html((string) ($lastLog['lines'] ?? '-')) ?>
                </span>
            </div>
        </div>
    </div>

    <?php
    if (isset($lastLog['content'])) {
        ?>
        <div class="log-container">
        <?php
        $logLines = explode("\n", $lastLog['content']);

        foreach ($logLines as $line) {
            $chunks = explode(' > ', $line);

            $date = $chunks[0] ?? null;
            $level = $chunks[1] ?? null;
            $message = $chunks[2] ?? null;

            if (!is_null($date) && !is_null($level) && !is_null($message)) {
                ?>
                <pre class="log-line"><?= esc_html($date) ?> &gt; <span class="<?= esc_attr('log-' . strtolower($level)) ?>"><?= esc_html($level) ?></span> &gt; <?= esc_html($message) ?></pre>
                <?php
            }
        }
        ?>
        </div>
        <?php
    }
    ?>
</div>
