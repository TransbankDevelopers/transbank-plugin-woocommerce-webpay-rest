<?php
if (!defined('ABSPATH')) {
    return;
}
$logDirectoryTitle = "Carpeta en el servidor en donde se guardan los archivos" .
    " con la información de cada compra mediante Webpay";
$lineCountTitle = "Cantidad de líneas que posee el último archivo de registro creado";
?>

<div class="tbk-box">
    <h3 class="tbk_title_h3">Información de Registros</h3>
    <div class="tbk-plugin-info-container">
        <div class="info-column">
            <div title="<?= $logDirectoryTitle ?>" class="label label-info">?</div>
        </div>
        <div class="info-column">
            <span class="highlight-text"> Directorio de registros: </span>
        </div>
        <div class="info-column">
            <span class="label">
                <?= $resume['dir'] ?>
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

                <select class="select label" name="log_file" <?= !$folderHasLogs ? "disabled" : '' ?>>
                    <?php
                    $options = '';

                    if (!$folderHasLogs) {
                        $options = "<option value='' selected>No hay archivos log</option>";
                    }
                    
                    foreach ($resume['logs'] as $index) {
                        if($index['filename'] == basename($lastLog['filename'])) {
                            $options .= "<option value='{$index['filename']}' selected>{$index['filename']}</option>";
                            continue;
                        }

                        $options .= "<option value='{$index['filename']}'>{$index['filename']}</option>";
                    }

                    echo $options;
                    ?>
                </select>
                <input type="submit" class="button button-primary tbk-button-primary"
                    <?= !$folderHasLogs ? 'disabled' : '' ?>
                    value="Ver">
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
                    <?= $lastLog['size'] ?? '-'; ?>
                </span>
            </div>
        </div>
        <div class="tbk-plugin-info-container">
            <div class="info-column">
                <div title="<?= $lineCountTitle ?>" class="label label-info">?</div>
            </div>
            <div class="info-column-plugin">
                <span class="highlight-text"> Cantidad de Líneas: </span>
            </div>
            <div class="info-column-plugin">
                <span class="label">
                    <?= $lastLog['lines'] ?? '-'; ?>
                </span>
            </div>
        </div>
    </div>

    <?php
    if (isset($lastLog['content'])) {
        $logContent = '<div class="log-container">';

        $logLines = explode("\n", $lastLog['content']);

        foreach ($logLines as $line) {
            $chunks = explode(' > ', $line);

            $date = $chunks[0];
            $level = $chunks[1] ?? null;
            $message = $chunks[2] ?? null;

            if (!is_null($date) && !is_null($level) && !is_null($message)) {
                $logContent .= '<pre class="log-line">' . $date . ' > ' .
                    '<span class="log-' . strtolower($level) . '">' . $level . '</span> > ' . $message .
                    '</pre>';
            }
        }
        $logContent .= '</div>';
        echo $logContent;
    }
    ?>
</div>
