<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="tbk-box">
    <div id="logs" class="tab-pane">
        <fieldset>

            <h3 class="tbk_title_h3">Información de Registros</h3>
            <table class="tbk_table_info">
                <tr style="display: none; visibility: hidden">
                    <td>
                        <div
                            title="Informa si actualmente se guarda la información de cada compra mediante Webpay"
                            class="label label-info">?
                        </div>
                        <strong>Estado de Registros: </strong></td>
                    <td class="tbk_table_td"><span id="action_txt"
                                                   class="label label-success">Registro activado</span><br>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div
                            title="Carpeta en el servidor en donde se guardan los archivos con la informacón de cada compra mediante Webpay"
                            class="label label-info">?
                        </div>
                        <strong>Directorio de registros: </strong></td>
                    <td class="tbk_table_td">
                        <?php echo json_decode($log->getResume(), true)['log_dir']; ?>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div
                            title="Cantidad de archivos que guardan la información de cada compra mediante Webpay"
                            class="label label-info">?
                        </div>
                        <strong>Cantidad de Registros en Directorio: </strong></td>
                    <td class="tbk_table_td">
                        <?php echo json_decode($log->getResume(), true)['logs_count']['log_count']; ?>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div
                            title="Lista los archivos archivos que guardan la información de cada compra mediante Webpay"
                            class="label label-info">?
                        </div>
                        <strong>Listado de Registros Disponibles: </strong></td>
                    <td class="tbk_table_td">
                        <ul style="font-size:0.8em;list-style: disc">
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
                        </ul>
                    </td>
                </tr>
            </table>
            <?php

            $lastLog = json_decode($log->getLastLog(), true); ?>
            <h3 class="tbk_title_h3">Últimos Registros</h3>
            <table class="tbk_table_info">
                <tr>
                    <td>
                        <div title="Nombre del útimo archivo de registro creado"
                             class="label label-info">?
                        </div>
                        <strong>Último Documento: </strong></td>
                    <td class="tbk_table_td">
                        <?php echo $lastLog['log_file'] ?? '-'; ?>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div title="Peso del último archivo de registro creado"
                             class="label label-info">?
                        </div>
                        <strong>Peso del Documento: </strong></td>
                    <td class="tbk_table_td">
                        <?php echo $lastLog['log_size'] ?? '-'; ?>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div title="Cantidad de líneas que posee el último archivo de registro creado"
                             class="label label-info">?
                        </div>
                        <strong>Cantidad de Líneas: </strong></td>
                    <td class="tbk_table_td">
                        <?php echo $lastLog['log_regs_lines'] ?? '-'; ?>
                    </td>
                </tr>
            </table>
            <br>
            <pre>
                        <span
                            style="font-size: 10px; padding: 10px; font-family:monospace; display: block; background: white;width: fit-content;">
    <?php echo $lastLog['log_content'] ?? '-'; ?>
                        </span>
                    </pre>
        </fieldset>
    </div>
</div>
