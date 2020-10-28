<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">

<link href="<?php echo plugin_dir_path( __FILE__ ) ?>css/bootstrap-switch.css" rel="stylesheet">
<link href="<?php echo plugin_dir_path( __FILE__ ) ?>ccss/tbk.css" rel="stylesheet">

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>

<h3><?php _e('Transbank Webpay', 'woocommerce'); ?></h3>
<p><?php _e('Transbank es la empresa líder en negocios de medio de pago seguros en Chile.'); ?></p>

<a class ="button " data-toggle="modal" href="#tb_modal">Información del sistema</a>

<hr>

<table class="form-table">
    <?php $this->generate_settings_html(); ?>
</table>

<div class="modal" id="tb_modal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <ul class="nav nav-tabs">
                    <li class="active" > <a data-toggle="tab" href="#info" class="tbk_tabs">Información</a></li>
                    <li> <a data-toggle="tab" href="#php_info" class="tbk_tabs">PHP info</a></li>
                    <li> <a data-toggle="tab" href="#logs" class="tbk_tabs">Registros</a></li>
                </ul>
            </div>
            <div class="modal-body">
                <div class="tab-content">
                    <div id="info" class="tab-pane in active">
                        <fieldset class="tbk_info">
                            <h3 class="tbk_title_h3">Informe pdf</h3>
                            <a class="button-primary" id="tbk_pdf_button"
                               href="<?=$this->plugin_url?>libwebpay/CreatePdf.php?document=report"
                               target="_blank">
                                Crear PDF
                            </a>
                        </fieldset>

                        <h3 class="tbk_title_h3">Información de Plugin / Ambiente</h3>
                        <table class="tbk_table_info">
                            <tr>
                                <td>
                                    <div title="Nombre del E-commerce instalado en el servidor" class="label label-info">?</div>
                                    <strong>Software E-commerce: </strong>
                                </td>
                                <td class="tbk_table_td">
                                    <?php echo $datos_hc->server_resume->plugin_info->ecommerce; ?>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div title="Versión de <?php echo $datos_hc->server_resume->plugin_info->ecommerce; ?> instalada en el servidor" class="label label-info">?</div>
                                    <strong>Version E-commerce: </strong>
                                </td>
                                <td class="tbk_table_td">
                                    <?php echo $datos_hc->server_resume->plugin_info->ecommerce_version; ?>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div title="Versión del plugin Webpay para <?php echo $datos_hc->server_resume->plugin_info->ecommerce; ?> instalada actualmente" class="label label-info">?</div>
                                    <strong>Versión actual del plugin: </strong>
                                </td>
                                <td class="tbk_table_td">
                                    <?php echo $datos_hc->server_resume->plugin_info->current_plugin_version; ?>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div title="Última versión del plugin Webpay para <?php echo $datos_hc->server_resume->plugin_info->ecommerce; ?> disponible" class="label label-info">?</div>
                                    <strong>Última versión del plugin: </strong>
                                </td>
                                <td class="tbk_table_td"
                                ><?php echo $datos_hc->server_resume->plugin_info->last_plugin_version; ?>
                                </td>
                            </tr>
                        </table>
                        <br>
                        <h3 class="tbk_title_h3">Estado de la Extensiones de PHP</h3>
                        <h4 class="tbk_table_title">Información Principal</h4>
                        <table class="tbk_table_info">
                            <tr>
                                <td>
                                    <div title="Descripción del Servidor Web instalado" class="label label-info">?</div>
                                    <strong>Software Servidor: </strong>
                                </td>
                                <td class="tbk_table_td">
                                    <?php echo $datos_hc->server_resume->server_version->server_software; ?>
                                </td>
                            </tr>
                        </table>
                        <hr>
                        <h4 class="tbk_table_title">PHP</h4>
                        <table class="tbk_table_info">
                            <tr>
                                <td>
                                    <div title="Informa si la versión de PHP instalada en el servidor es compatible con el plugin de Webpay" class="label label-info">?</div>
                                    <strong>Estado de PHP</strong>
                                </td>
                                <td class="tbk_table_td"><span class="label
                                                <?php if ($datos_hc->server_resume->php_version->status == 'OK') {
                                        echo 'label-success';
                                    } else {
                                        echo 'label-danger';
                                    } ?>">
												<?php echo $datos_hc->server_resume->php_version->status; ?>
                                                </span>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div title="Versión de PHP instalada en el servidor" class="label label-info">?</div>
                                    <strong>Versión: </strong></td>
                                <td class="tbk_table_td">
                                    <?php echo $datos_hc->server_resume->php_version->version; ?>
                                </td>
                            </tr>
                        </table>
                        <hr>
                        <h4 class="tbk_table_title">Extensiones PHP requeridas</h4>
                        <table class="table table-responsive table-striped">
                            <tr>
                                <th>Extensión</th>
                                <th>Estado</th>
                                <th class="tbk_table_td">Versión</th>
                            </tr>
                            <tr>
                                <td style="font-weight:bold">openssl</td>
                                <td>
                                                <span class="label
                                                <?php if ($datos_hc->php_extensions_status->openssl->status == 'OK') {
                                                    echo 'label-success';
                                                } else {
                                                    echo 'label-danger';
                                                } ?>">
												<?php echo $datos_hc->php_extensions_status->openssl->status; ?>
                                                </span>
                                </td>
                                <td class="tbk_table_td">
                                    <?php echo $datos_hc->php_extensions_status->openssl->version; ?>
                                </td>
                            </tr>
                            <tr>
                                <td style="font-weight:bold">SimpleXml</td>
                                <td>
                                                <span class="label
                                                <?php if ($datos_hc->php_extensions_status->SimpleXML->status == 'OK') {
                                                    echo 'label-success';
                                                } else {
                                                    echo 'label-danger';
                                                } ?>">
												<?php echo $datos_hc->php_extensions_status->SimpleXML->status; ?>
                                                </span>
                                </td>
                                <td class="tbk_table_td">
                                    <?php echo $datos_hc->php_extensions_status->SimpleXML->version; ?>
                                </td>
                            </tr>

                            <tr>
                                <td style="font-weight:bold">dom</td>
                                <td>
                                                <span class="label
                                                <?php if ($datos_hc->php_extensions_status->dom->status == 'OK') {
                                                    echo 'label-success';
                                                } else {
                                                    echo 'label-danger';
                                                } ?>">
												<?php echo $datos_hc->php_extensions_status->dom->status; ?>
                                                </span>
                                </td>
                                <td class="tbk_table_td">
                                    <?php echo $datos_hc->php_extensions_status->dom->version; ?>
                                </td>
                            </tr>
                        </table>
                        <br>

                        <h3 class="menu-head">Validaci&oacute;n Transacci&oacute;n</h3>
                        <h4>Petici&oacute;n a Transbank</h4>
                        <table class="table table-striped">
                            <tbody>
                            <tr>
                                <td>
                                    <button class="check_conn btn btn-sm btn-primary">Verificar Conexión</button>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                        <hr>
                        <h4>Respuesta de Transbank</h4>
                        <table class="table table-borderless">
                            <tbody>
                            <tr id="row_response_status" style="display:none">
                                <td>
                                    <div title="Informa el estado de la comunicación con Transbank mediante método create_transaction" class="label label-info">?</div> <strong>Estado: </strong>
                                </td>
                                <td>
                                    <span id="row_response_status_text" class="label tbk_table_trans" style="display:none"></span>
                                </td>
                            </tr>
                            <tr id="row_response_url" style="display:none">
                                <td>
                                    <div title="URL entregada por Transbank para realizar la transacción" class="label label-info">?</div> <strong>URL: </strong>
                                </td>
                                <td id="row_response_url_text" class="tbk_table_trans"></td>
                            </tr>
                            <tr id="row_response_token" style="display:none">
                                <td>
                                    <div title="Token entregada por Transbank para realizar la transacción" class="label label-info">?</div> <strong>Token: </strong>
                                </td>
                                <td id="row_response_token_text" class="tbk_table_trans"></td>
                            </tr>
                            <tr id="row_error_message" style="display:none">
                                <td>
                                    <div title="Mensaje de error devuelto por Transbank al fallar init_transaction" class="label label-info">?</div> <strong>Error: </strong>
                                </td>
                                <td id="row_error_message_text" class="tbk_table_trans"></td>
                            </tr>
                            <tr id="row_error_detail" style="display:none">
                                <td>
                                    <div title="Detalle del error devuelto por Transbank al fallar init_transaction" class="label label-info">?</div> <strong>Detalle: </strong>
                                </td>
                                <td id="row_error_detail_text" class="tbk_table_trans"></td>
                            </tr>
                            </tbody>
                        </table>

                    </div>

                    <div id="php_info" class="tab-pane">
                        <fieldset class="tbk_info">
                            <h3 class="tbk_title_h3">Informe PHP info</h3>
                            <a class="button-primary" href="<?=$this->plugin_url?>libwebpay/CreatePdf.php?document=php_info" target="_blank">
                                Crear PHP info
                            </a>
                            <br>
                        </fieldset>

                        <fieldset>
                            <h3 class="tbk_title_h3">PHP info</h3>
                            <span style="font-size: 10px; font-family:monospace; display: block; background: white;overflow: hidden;" >
											<?php echo $datos_hc->php_info->string->content; ?>
										</span><br>
                        </fieldset>
                    </div>

                    <div id="logs" class="tab-pane">
                        <fieldset>
                            <div style="visibility: hidden; display: none">
                                <h3 class="tbk_title_h3">Configuración</h3>
                                <?php
                                $log_days = isset($this->log->getValidateLockFile()['max_logs_days']) ? $this->log->getValidateLockFile()['max_logs_days'] : null;
                                $log_size = isset($this->log->getValidateLockFile()['max_log_weight']) ? $this->log->getValidateLockFile()[ 'max_log_weight'] : null;
                                $lockfile = json_decode($this->log->getLockFile(), true)['status'];
                                ?>
                                <table class="tbk_table_info">
                                    <tr>
                                        <td><div title="Al activar esta opción se habilita que se guarden los datos de cada compra mediante Webpay" class="label label-info">?</div> <strong>Activar Registro: </strong></td>
                                        <td class="tbk_table_td">
                                            <?php if ($lockfile) {
                                                echo '<input type="checkbox" id="action_check" name="action_check" checked data-size="small" value="activate">
                                                                <script>
                                                                        document.cookie="action_check=true; path=/";
                                                                </script>';
                                            } else {
                                                echo '<input type="checkbox" id="action_check" name="action_check" data-size="small" state="false">';
                                            } ?>
                                        </td>
                                    </tr>
                                </table>
                                <table class="tbk_table_info">
                                    <tr>
                                        <td><div title="Cantidad de días que se conservan los datos de cada compra mediante Webpay" class="label label-info">?</div> <strong>Cantidad de Dias a Registrar</strong></td>
                                        <td class="tbk_table_td"><input id="days" name="days" type="number" min="1" max="30" value="<?php echo $log_days; ?>"> días</td>
                                    </tr>
                                    <tr>
                                        <td><div title="Peso máximo (en Megabytes) de cada archivo que guarda los datos de las compras mediante Webpay" class="label label-info">?</div> <strong>Peso máximo de Registros: </strong></td>
                                        <td class="tbk_table_td"><select style="width: 100px; display: initial;" id="size" name="size">
                                                <?php for ($c = 1; $c < 10; $c++) {
                                                    echo '<option value="' . $c . '"';
                                                    if ($c == $log_size) {
                                                        echo ' selected';
                                                    }
                                                    echo '>' . $c . '</option>';
                                                } ?>
                                            </select> Mb</td>
                                    </tr>
                                </table>
                                <div class="tbk_btn tbk_danger_btn" onclick="javascript:updateConfig()" href="" target="_blank">
                                    Actualizar Parámetros
                                </div>
                            </div>

                            <h3 class="tbk_title_h3">Información de Registros</h3>
                            <table class="tbk_table_info">
                                <tr style="display: none; visibility: hidden">
                                    <td><div title="Informa si actualmente se guarda la información de cada compra mediante Webpay" class="label label-info">?</div> <strong>Estado de Registros: </strong></td>
                                    <td class="tbk_table_td"><span id="action_txt" class="label label-success">Registro activado</span><br> </td>
                                </tr>
                                <tr>
                                    <td><div title="Carpeta en el servidor en donde se guardan los archivos con la informacón de cada compra mediante Webpay" class="label label-info">?</div> <strong>Directorio de registros: </strong></td>
                                    <td class="tbk_table_td">
                                        <?php echo json_decode($this->log->getResume(), true)['log_dir']; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><div title="Cantidad de archivos que guardan la información de cada compra mediante Webpay" class="label label-info">?</div> <strong>Cantidad de Registros en Directorio: </strong></td>
                                    <td class="tbk_table_td">
                                        <?php echo json_decode($this->log->getResume(), true)['logs_count']['log_count']; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><div title="Lista los archivos archivos que guardan la información de cada compra mediante Webpay" class="label label-info">?</div> <strong>Listado de Registros Disponibles: </strong></td>
                                    <td class="tbk_table_td">
                                        <ul style="font-size:0.8em;list-style: disc">
                                            <?php
                                            $logs_list = isset(json_decode($this->log->getResume(), true)['logs_list']) ? json_decode($this->log->getResume(), true)['logs_list']: array();
                                            foreach ($logs_list as $index) {
                                                echo '<li>' . $index . '</li>';
                                            }
                                            ?>
                                        </ul>
                                    </td>
                                </tr>
                            </table>

                            <h3 class="tbk_title_h3">Últimos Registros</h3>
                            <table class="tbk_table_info">
                                <tr>
                                    <td><div title="Nombre del útimo archivo de registro creado" class="label label-info">?</div> <strong>Último Documento: </strong></td>
                                    <td class="tbk_table_td">
                                        <?php echo isset(json_decode($this->log->getLastLog(), true)['log_file']) ? json_decode($this->log->getLastLog(), true)['log_file'] : null; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><div title="Peso del último archivo de registro creado" class="label label-info">?</div> <strong>Peso del Documento: </strong></td>
                                    <td class="tbk_table_td">
                                        <?php echo isset(json_decode($this->log->getLastLog(), true)['log_weight']) ? json_decode($this->log->getLastLog(), true)['log_weight'] : null; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><div title="Cantidad de líneas que posee el último archivo de registro creado" class="label label-info">?</div> <strong>Cantidad de Líneas: </strong></td>
                                    <td class="tbk_table_td">
                                        <?php echo isset(json_decode($this->log->getLastLog(), true)['log_regs_lines']) ? json_decode($this->log->getLastLog(), true)['log_regs_lines'] : null; ?>
                                    </td>
                                </tr>
                            </table>
                            <br>
                            <pre>
											<span style="font-size: 10px; font-family:monospace; display: block; background: white;width: fit-content;" >
											<?php echo isset(json_decode($this->log->getLastLog(), true)['log_content']) ? json_decode($this->log->getLastLog(), true)['log_content'] : null; ?>
                                            </span>
                                        </pre>
                        </fieldset>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
