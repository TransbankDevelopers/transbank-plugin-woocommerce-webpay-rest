<?php
if (!defined('ABSPATH')) {
    return;
}
?>

<div class="tbk-box">
    <div id="logs" class="tab-pane">
        <h3 class="tbk_title_h3">Información de Registros</h3>
        <div id="tbk_information_logs" style="margin-bottom: 30px;">
            <div class="tbk-plugin-info-container" id="div_status_logs">
                <div class="info-column">
                    <div title="Informa si actualmente se guarda la información de cada compra mediante Webpay"
                        class="label label-info">?</div>
                </div>
                <div class="info-column">
                    <span class="highlight-text"> Estado de Registros: </span>
                </div>
                <div class="info-column">
                    <span id="action_txt" class="label label-success">Registro activado</span>
                </div>
            </div>
            <div class="tbk-plugin-info-container" id="div_logs_path">
                <div class="info-column">
                    <div title="Carpeta en el servidor en donde se guardan los archivos con la información de cada compra mediante Webpay" class="label label-info">?
                    </div>
                </div>
                <div class="info-column-plugin log">
                    <span class="highlight-text"> Directorio de registros: </span>
                </div>
                <div class="info-column-plugin log">
                    <span class="label">
                        <?php echo $resume['dir']; ?>
                    </span>
                </div>
            </div>
            <div class="tbk-plugin-info-container" id="div_logs_number">
                <div class="info-column">
                    <div title="Cantidad de archivos que guardan la información de cada compra mediante Webpay"
                        class="label label-info">?</div>
                </div>
                <div class="info-column-plugin log">
                    <span class="highlight-text"> Cantidad de Registros en Directorio: </span>
                </div>
                <div class="info-column-plugin log">
                    <span class="label">
                        <?php echo $resume['length']; ?>
                    </span>
                </div>
            </div>
            <?php if ($folderHasLogs) { ?>
                <div class="tbk-plugin-info-container" id="div_logs_list">
                    <div class="info-column">
                        <div title="Lista los archivos que guardan la información de cada compra mediante Webpay"
                        class="label label-info">?</div>
                    </div>
                    <div class="info-column-plugin log">
                        <span class="highlight-text"> Listado de Registros Disponibles: </span>
                    </div>
                    <div class="info-column-plugin log">
                        <span class="label">
                            <?php
                            foreach ($resume['logs'] as $index) {
                                $str = '<li>' . $index['filename'] . '</li>';
                                echo $str;
                            }
                            ?>
                        </span>
                    </div>
                </div>
            <?php } ?>
        </div>

        <?php if ($folderHasLogs) { ?>
            <h3 class="tbk_title_h3">Últimos Registros</h3>
            <div id="tbk-last-logs">
                <div class="tbk-plugin-info-container" id="div_last_log">
                    <div class="info-column">
                        <div title="Nombre del útimo archivo de registro creado" class="label label-info">?
                        </div>
                    </div>
                    <div class="info-column-plugin">
                        <span class="highlight-text"> Último Documento: </span>
                    </div>
                    <div class="info-column-plugin">
                        <span class="label">
                            <?php echo $lastLog['filename'] ?? '-'; ?>
                        </span>
                    </div>
                </div>
                <div class="tbk-plugin-info-container" id="div_size_log">
                    <div class="info-column">
                        <div title="Peso del último archivo de registro creado" class="label label-info">?
                        </div>
                    </div>
                    <div class="info-column-plugin">
                        <span class="highlight-text"> Peso del Documento: </span>
                    </div>
                    <div class="info-column-plugin">
                        <span class="label">
                            <?php echo $lastLog['size'] ?? '-'; ?>
                        </span>
                    </div>
                </div>
                <div class="tbk-plugin-info-container" id="div_lines_logs">
                    <div class="info-column">
                        <div title="Cantidad de líneas que posee el último archivo de registro creado"
                        class="label label-info">?</div>
                    </div>
                    <div class="info-column-plugin">
                        <span class="highlight-text"> Cantidad de Líneas: </span>
                    </div>
                    <div class="info-column-plugin">
                        <span class="label">
                            <?php echo $lastLog['lines'] ?? '-'; ?>
                        </span>
                    </div>
                </div>
            </div>

            <?php
            if (!is_null($lastLog['content'])) {
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
        <?php } ?>
    </div>
</div>
