<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="tbk-box">
    <div id="logs" class="tab-pane">
        <h3 class="tbk_title_h3">Información de Registros</h3>
        <div id="tbk_information_logs" style="margin-bottom: 30px;">
            <div class="tbk-plugin-info-container logs" id="div_status_logs">
                <div class="info-column">
                    <div title="Informa si actualmente se guarda la información de cada compra mediante Webpay"
                         class="label label-info">?
                    </div>
                </div>
                <div class="info-column">
                    <span class="highlight-text"> Estado de Registros:  </span>
                </div>
                <div class="info-column">
                    <span id="action_txt" class="label label-success">Registro activado</span>
                </div>
            </div>
            <div class="tbk-plugin-info-container logs" id="div_logs_path" >
                <div class="info-column">
                    <div title="Carpeta en el servidor en donde se guardan los archivos con la informacón de cada compra mediante Webpay"
                         class="label label-info">?
                    </div>
                </div>
                <div class="info-column-plugin log">
                    <span class="highlight-text"> Directorio de registros:  </span>
                </div>
                <div class="info-column-plugin log">
                <span class="label">
                    <?php echo json_decode($log->getResume(), true)['log_dir']; ?>
                </span>
                </div>
            </div>
            <div class="tbk-plugin-info-container logs" id="div_logs_number">
                <div class="info-column">
                    <div title="Cantidad de archivos que guardan la información de cada compra mediante Webpay"
                         class="label label-info">?
                    </div>
                </div>
                <div class="info-column-plugin log">
                    <span class="highlight-text"> Cantidad de Registros en Directorio:  </span>
                </div>
                <div class="info-column-plugin log">
                <span class="label">
                   <?php echo json_decode($log->getResume(), true)['logs_count']['log_count']; ?>
                </span>
                </div>
            </div>
            <div class="tbk-plugin-info-container logs" id="div_logs_list">
                <div class="info-column">
                    <div title="Lista los archivos archivos que guardan la información de cada compra mediante Webpay"
                         class="label label-info">?
                    </div>
                </div>
                <div class="info-column-plugin log">
                    <span class="highlight-text"> Listado de Registros Disponibles:  </span>
                </div>
                <div class="info-column-plugin log">
                <span class="label">
                   <?php
                   $logs_list = isset(json_decode(
                           $log->getResume(),
                           true
                       )['logs_list']) ? json_decode(
                       $log->getResume(),
                       true
                   )['logs_list'] : [];
                   foreach ($logs_list as $index) {
                       echo '<li>'.$index.'</li>';
                   }
                   ?>
                </span>
                </div>
            </div>
        </div>


        <h3 class="tbk_title_h3">Últimos Registros</h3>
        <?php $lastLog = json_decode($log->getLastLog(), true); ?>
        <div id="tbk-last-logs" >
            <div class="tbk-plugin-info-container logs" id="div_last_log">
                <div class="info-column">
                    <div title="Nombre del útimo archivo de registro creado"
                         class="label label-info">?
                    </div>
                </div>
                <div class="info-column-plugin">
                    <span class="highlight-text"> Último Documento: </span>
                </div>
                <div class="info-column-plugin">
                        <span class="label">
                            <?php echo $lastLog['log_file'] ?? '-'; ?>
                        </span>
                </div>
            </div>
            <div class="tbk-plugin-info-container logs" id="div_size_log">
                <div class="info-column">
                    <div title="Peso del último archivo de registro creado"
                         class="label label-info">?
                    </div>
                </div>
                <div class="info-column-plugin">
                    <span class="highlight-text"> Peso del Documento: </span>
                </div>
                <div class="info-column-plugin">
                        <span class="label">
                            <?php echo $lastLog['log_size'] ?? '-'; ?>
                        </span>
                </div>
            </div>
            <div class="tbk-plugin-info-container logs" id="div_lines_logs">
                <div class="info-column">
                    <div title="Cantidad de líneas que posee el último archivo de registro creado"
                         class="label label-info">?
                    </div>
                </div>
                <div class="info-column-plugin">
                    <span class="highlight-text"> Cantidad de Líneas: </span>
                </div>
                <div class="info-column-plugin">
                        <span class="label">
                            <?php echo $lastLog['log_regs_lines'] ?? '-'; ?>
                        </span>
                </div>
            </div>
        </div>

        <div id="tbk_last_log_content">
            <pre>
            <span style="font-size: 10px; padding: 5px; font-family:monospace; display: block; background: white;">
                <?php echo $lastLog['log_content'] ?? '-'; ?>
            </span>
        </pre>
        </div>
    </div>
</div>
