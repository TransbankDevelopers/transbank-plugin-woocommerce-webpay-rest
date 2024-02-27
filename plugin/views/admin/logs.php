<?php

use Transbank\WooCommerce\WebpayRest\Helpers\TbkFactory;

if (!defined('ABSPATH')) {
    return;
}

$log = TbkFactory::createLogger();
$resume = $log->getInfo();
$lastLog = $log->getLogDetail(basename($resume['last']));
$folderHasLogs = $resume['length'] > 0;
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
                $logContent = '';
                $logContent .= '<div class="log-container">';

                $lineas_logs = explode("\n", $lastLog['content']);

                foreach ($lineas_logs as $linea) {
                    $partes = explode(' > ', $linea);

                    $fecha_hora = $partes[0];
                    $nivel = $partes[1] ?? null;
                    $mensaje = $partes[2] ?? null;

                    switch ($nivel) {
                        case 'INFO':
                            $clase_nivel = 'log-info';
                            break;
                        case 'ERROR':
                            $clase_nivel = 'log-error';
                            break;
                        case 'WARNING':
                            $clase_nivel = 'log-warning';
                            break;
                        case 'DEBUG':
                            $clase_nivel = 'log-debug';
                            break;
                        default:
                            $clase_nivel = '';
                    }

                    if (!is_null($fecha_hora) && !is_null($nivel) && !is_null($mensaje)) {
                        $logContent .= '<pre class="log-line">' . $fecha_hora . ' > ' .
                            '<span class="log-' . strtolower($nivel) . '">' . $nivel . '</span> > ' . $mensaje .
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
