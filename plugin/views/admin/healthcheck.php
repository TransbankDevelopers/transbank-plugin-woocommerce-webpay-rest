<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="transbank_rest_info" class="">

    <div class="transbank_rest_tool">
        <h3 style="margin-bottom: 0">Verificar existencia de las tablas del plugin</h3>
        <p>Esta herramienta permite verificar que las tablas asociadas al Plugin de Transbank existan en la base de datos.</p>
        <div>
            <ul>
                <li>Si no existe(n) se fuerza la creación al momento de hacer click.</li>
                <li>Si arroja algún error debes volver a verificar.</li>
                <li>Si los errores persisten puedes obtener más información en el tab de "registros (logs)".</li>
            </ul>
        </div>  
        <table class="table table-striped">
            <tbody>
            <tr>
                <td>
                    <button class="check_exist_tables button">Verificar Tablas</button>
                </td>
            </tr>
            </tbody>
        </table>
        <hr>
        <h4 class="tbk-tbl-response-title" style="display: none">Respuesta</h4>
        <table class="table table-borderless">
            <tbody>
            <tr id="row_tbl_response_status" style="display:none">
                <td>
                    <div
                        title="Informa el estado de la verificación de la existencia de las tablas de plugin"
                        class="label label-info">?
                    </div>
                    <strong>Estado: </strong>
                </td>
                <td>
                                    <span id="row_tbl_response_status_text" class="label tbk_tbl_table_trans"
                                          style="display:none"></span>
                </td>
            </tr>
            <tr id="row_tbl_response_result" style="display:none">
                <td>
                    <div title="Resultado de la verificación"
                         class="label label-info">?
                    </div>
                    <strong>Resultado: </strong>
                </td>
                <td id="row_tbl_response_result_text" class="tbk_tbl_table_trans"></td>
            </tr>
            <tr id="row_tbl_error_message" style="display:none">
                <td>
                    <div title="Mensaje de error devuelto por la verificación y Wordpress"
                         class="label label-info">?
                    </div>
                    <strong>Error: </strong>
                </td>
                <td id="row_tbl_error_message_text" class="tbk_tbl_table_trans"></td>
            </tr>
            </tbody>
        </table>
    </div>

    <div class="transbank_rest_tool">
        <h3 style="margin-bottom: 0">Verificar configuración Webpay Plus </h3>
        <p>Esta herramienta permite probar tu configuración (ambiente, código de comercio y llave secreta (Api Key
            Secret). Al verificar conexión se envía una solicitud para crear una transacción de prueba.
            Si ocurre algún problema, puedes obtener más información en el tab de "registros (logs)"</p>
        <table class="table table-striped">
            <tbody>
            <tr>
                <td>
                    <button class="check_conn button">Verificar Conexión</button>
                </td>
            </tr>
            </tbody>
        </table>
        <hr>
        <h4 class="tbk-response-title" style="display: none">Respuesta de Transbank</h4>
        <table class="table table-borderless">
            <tbody>
            <tr id="row_response_status" style="display:none">
                <td>
                    <div
                        title="Informa el estado de la comunicación con Transbank mediante método create_transaction"
                        class="label label-info">?
                    </div>
                    <strong>Estado: </strong>
                </td>
                <td>
                                    <span id="row_response_status_text" class="label tbk_table_trans"
                                          style="display:none"></span>
                </td>
            </tr>
            <tr id="row_response_url" style="display:none">
                <td>
                    <div title="URL entregada por Transbank para realizar la transacción"
                         class="label label-info">?
                    </div>
                    <strong>URL: </strong>
                </td>
                <td id="row_response_url_text" class="tbk_table_trans"></td>
            </tr>
            <tr id="row_response_token" style="display:none">
                <td>
                    <div title="Token entregada por Transbank para realizar la transacción"
                         class="label label-info">?
                    </div>
                    <strong>Token: </strong>
                </td>
                <td id="row_response_token_text" class="tbk_table_trans"></td>
            </tr>
            <tr id="row_error_message" style="display:none">
                <td>
                    <div title="Mensaje de error devuelto por Transbank al fallar init_transaction"
                         class="label label-info">?
                    </div>
                    <strong>Error: </strong>
                </td>
                <td id="row_error_message_text" class="tbk_table_trans"></td>
            </tr>
            <tr id="row_error_detail" style="display:none">
                <td>
                    <div title="Detalle del error devuelto por Transbank al fallar init_transaction"
                         class="label label-info">?
                    </div>
                    <strong>Detalle: </strong>
                </td>
                <td id="row_error_detail_text" class="tbk_table_trans"></td>
            </tr>
            </tbody>
        </table>
    </div>

    <div class="tbk-box">
        <fieldset class="tbk_info">
            <a class="button-primary" id="tbk_pdf_button"
               href="<?php echo admin_url('admin-ajax.php'); ?>?action=download_report&document=report"
               target="_blank">
                Descargar diagnóstico en PDF
            </a>
        </fieldset>


        <h3 class="tbk_title_h3">Información de Plugin / Ambiente</h3>
        <table class="tbk_table_info">
            <tr>
                <td>
                    <div title="Nombre del E-commerce instalado en el servidor"
                         class="label label-info">?
                    </div>
                    <strong>Software E-commerce: </strong>
                </td>
                <td class="tbk_table_td">
                    <?php echo $datos_hc->server_resume->plugin_info->ecommerce; ?>
                </td>
            </tr>
            <tr>
                <td>
                    <div
                        title="Versión de <?php echo $datos_hc->server_resume->plugin_info->ecommerce; ?> instalada en el servidor"
                        class="label label-info">?
                    </div>
                    <strong>Version E-commerce: </strong>
                </td>
                <td class="tbk_table_td">
                    <?php echo $datos_hc->server_resume->plugin_info->ecommerce_version; ?>
                </td>
            </tr>
            <tr>
                <td>
                    <div
                        title="Versión del plugin Webpay para <?php echo $datos_hc->server_resume->plugin_info->ecommerce; ?> instalada actualmente"
                        class="label label-info">?
                    </div>
                    <strong>Versión actual del plugin: </strong>
                </td>
                <td class="tbk_table_td">
                    <?php echo $datos_hc->server_resume->plugin_info->current_plugin_version; ?>
                </td>
            </tr>
            <tr>
                <td>
                    <div
                        title="Última versión del plugin Webpay para <?php echo $datos_hc->server_resume->plugin_info->ecommerce; ?> disponible"
                        class="label label-info">?
                    </div>
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
        <h4 class="tbk_table_title">PHP</h4>
        <table class="tbk_table_info">
            <tr>
                <td>
                    <div
                        title="Informa si la versión de PHP instalada en el servidor es compatible con el plugin de Webpay"
                        class="label label-info">?
                    </div>
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
                    <div title="Versión de PHP instalada en el servidor" class="label label-info">?
                    </div>
                    <strong>Versión: </strong></td>
                <td class="tbk_table_td">
                    <?php echo $datos_hc->server_resume->php_version->version; ?>
                </td>
            </tr>
        </table>
        <h4 class="tbk_table_title">Extensiones PHP requeridas</h4>
        <table class="tbk_table_info">
            <tr>
                <th>Extensión</th>
                <th>Estado</th>
                <th class="tbk_table_td">Versión</th>
            </tr>
            <tr>
                <td style="font-weight:bold">json</td>
                <td>
                                                <span class="label
                                                <?php if ($datos_hc->php_extensions_status->json->status == 'OK') {
    echo 'label-success';
} else {
    echo 'label-danger';
} ?>">
                                                <?php echo $datos_hc->php_extensions_status->json->status; ?>
                                                </span>
                </td>
                <td class="tbk_table_td">
                    <?php echo $datos_hc->php_extensions_status->json->version; ?>
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
            <tr>
                <td style="font-weight:bold">curl</td>
                <td>
                                                <span class="label
                                                <?php if ($datos_hc->php_extensions_status->curl->status == 'OK') {
    echo 'label-success';
} else {
    echo 'label-danger';
} ?>">
                                                <?php echo $datos_hc->php_extensions_status->curl->status; ?>
                                                </span>
                </td>
                <td class="tbk_table_td">
                    <?php echo $datos_hc->php_extensions_status->curl->version; ?>
                </td>
            </tr>
        </table>
        <br>
    </div>


</div>



