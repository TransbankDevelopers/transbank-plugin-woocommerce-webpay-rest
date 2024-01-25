<?php

use Transbank\Plugin\Helpers\InfoUtil;
use Transbank\Plugin\Helpers\WoocommerceInfoUtil;
if (!defined('ABSPATH')) {
    return;
}

$summary = InfoUtil::getSummary();
$eSummary = WoocommerceInfoUtil::getSummary();
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
        <div>
            <button class="check_exist_tables button">Verificar Tablas</button>
        </div>
        <hr>
        <h4 id="tbk-tbl-response-title" class="tbk-hide">Respuesta</h4>
        <div class="tbk-response-container tbk-hide" id="div_tables_status">
            <div class="info-column">
                <div title="Informa el estado de la verificación de la existencia de las tablas de plugin"
                     class="label label-info">?
                </div>
            </div>
            <div class="info-column">
                <span class="highlight-text"> Estado: </span>
            </div>
            <div class="info-column">
                <span class="label" id="tbl_response_status_text"></span>
            </div>
        </div>
        <div class="tbk-response-container tbk-hide" id="div_tables_status_result">
            <div class="info-column">
                <div title="Resultado de la verificación de tablas"
                     class="label label-info">?
                </div>
            </div>
            <div class="info-column">
                <span class="highlight-text"> Resultado: </span>
            </div>
            <div class="info-column">
                <span class="label" id="tbl_response_result_text"></span>
            </div>
        </div>
        <div class="tbk-response-container tbk-hide" id="div_tables_error">
            <div class="info-column">
                <div title="Error en la verificación de existencia de tablas"
                     class="label label-info">?
                </div>
            </div>
            <div class="info-column">
                <span class="highlight-text"> Error: </span>
            </div>
            <div class="info-column">
                <span class="label" id="tbl_error_message_text"></span>
            </div>
        </div>
    </div>

    <div class="transbank_rest_tool">
        <h3 style="margin-bottom: 0">Verificar configuración Webpay Plus </h3>
        <p>Esta herramienta permite probar tu configuración (ambiente, código de comercio y llave secreta (Api Key
            Secret). Al verificar conexión se envía una solicitud para crear una transacción de prueba.
            Si ocurre algún problema, puedes obtener más información en el tab de "registros (logs)"</p>
        <div>
            <button class="check_conn button">Verificar Conexión</button>
        </div>
        <hr>
        <div class="tbk-response-status tbk-hide" id="tbk_response_status">
            <h4 id="response_title">Respuesta de Transbank</h4>
            <div class="tbk-status-ok tbk-hide" id="div_status_ok">
                <div class="tbk-response-container" id="div_status">
                    <div class="info-column">
                        <div title="Informa el estado de la comunicación con Transbank mediante método create_transaction"
                             class="label label-info">?
                        </div>
                    </div>
                    <div class="info-column">
                        <span class="highlight-text"> Estado: </span>
                    </div>
                    <div class="info-column">
                        <span class="label label-success" id="response_status_text">OK</span>
                    </div>
                </div>
                <div class="tbk-response-container" id="div_response_url">
                    <div class="info-column">
                        <div title="URL entregada por Transbank para realizar la transacción"
                             class="label label-info">?
                        </div>
                    </div>
                    <div class="info-column">
                        <span class="highlight-text"> URL: </span>
                    </div>
                    <div class="info-column" id="response_url_text">
                    </div>
                </div>
                <div class="tbk-response-container" id="div_response_token">
                    <div class="info-column">
                        <div title="Token entregada por Transbank para realizar la transacción"
                             class="label label-info">?
                        </div>
                    </div>
                    <div class="info-column">
                        <span class="highlight-text"> Token: </span>
                    </div>
                    <div class="info-column token" id="response_token_text">
                    </div>
                </div>
            </div>
            <div class="tbk-status-error tbk-hide" id="div_status_error">
                <div class="tbk-response-container" id="div_error_status">
                    <div class="info-column">
                        <div title="Status devuelto por Transbank al fallar create_transaction"
                             class="label label-info">?
                        </div>
                    </div>
                    <div class="info-column">
                        <span class="highlight-text"> Estado: </span>
                    </div>
                    <div class="info-column" id="error_response_status">
                        <span class="label label-danger" id="error_response_status_text">ERROR</span>
                    </div>
                </div>
                <div class="tbk-response-container" id="div_error_message">
                    <div class="info-column">
                        <div title="Mensaje de error devuelto por Transbank al fallar create_transaction"
                             class="label label-info">?
                        </div>
                    </div>
                    <div class="info-column">
                        <span class="highlight-text"> Error: </span>
                    </div>
                    <div class="info-column" id="error_response_text">
                    </div>
                </div>
                <div class="tbk-response-container" id="div_error_detail">
                    <div class="info-column">
                        <div title="Detalle del error devuelto por Transbank al fallar create_transaction"
                             class="label label-info">?
                        </div>
                    </div>
                    <div class="info-column">
                        <span class="highlight-text"> Detalle: </span>
                    </div>
                    <div class="info-column" id="error_detail_response_text">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="transbank_rest_tool">
        <h3 class="tbk_title_h3">Información de Plugin / Ambiente</h3>
        <div class="tbk-plugin-info-container">
            <div class="info-column-plugin">
                <div title="Nombre del E-commerce instalado en el servidor"
                     class="label label-info">?
                </div>
            </div>
            <div class="info-column-plugin">
                <span class="highlight-text"> Software E-commerce: </span>
            </div>
            <div class="info-column-plugin">
                <span class="label" id="software_ecommerce">
                    <?php echo $eSummary['ecommerce']; ?>
                </span>
            </div>
            <div class="info-column-plugin">
                <div title="Versión de <?php echo $eSummary['ecommerce']; ?> instalada en el servidor"
                     class="label label-info">?
                </div>
            </div>
            <div class="info-column-plugin">
                <span class="highlight-text"> Version E-commerce: </span>
            </div>
            <div class="info-column-plugin">
                <span class="label" id="version_ecommerce">
                    <?php echo $eSummary['currentEcommerceVersion']; ?>
                </span>
            </div>
            <div class="info-column-plugin">
                <div title="Versión del plugin Webpay para <?php echo $eSummary['ecommerce']; ?> instalada actualmente"
                     class="label label-info">?
                </div>
            </div>
            <div class="info-column-plugin">
                <span class="highlight-text"> Versión actual del plugin: </span>
            </div>
            <div class="info-column-plugin">
                <span class="label" id="current_version_plugin">
                    <?php echo $eSummary['currentPluginVersion']; ?>
                </span>
            </div>
            <div class="info-column-plugin">
                <div title="Última versión del plugin Webpay para <?php echo $eSummary['ecommerce']; ?> disponible"
                     class="label label-info">?
                </div>
            </div>
            <div class="info-column-plugin">
                <span class="highlight-text"> Última versión del plugin: </span>
            </div>
            <div class="info-column-plugin">
                <span class="label" id="last_version_available">
                    <?php echo $eSummary['lastPluginVersion']; ?>
                </span>
            </div>
        </div>
        <hr>
        <h3 class="tbk_title_h3">Estado de la Extensiones de PHP</h3>
        <h4 class="tbk_table_title">Información Principal</h4>
        <div class="tbk-plugin-info-container">
            <div class="info-column-plugin">
                <div title="Descripción del Servidor Web instalado"
                     class="label label-info">?
                </div>
            </div>
            <div class="info-column-plugin">
                <span class="highlight-text"> Software Servidor: </span>
            </div>
            <div class="info-column-plugin">
                <span class="label" id="software_server">
                    <?php echo $summary['server']; ?>
                </span>
            </div>
            <div class="info-column-plugin">
                <div title="Informa si la versión de PHP instalada en el servidor es compatible con el plugin de Webpay"
                     class="label label-info">?
                </div>
            </div>
            <div class="info-column-plugin">
                <span class="highlight-text"> Estado de PHP: </span>
            </div>
            <div class="info-column-plugin">
                <span class="label
                   <?php if ($summary['php']['status'] == 'OK') {
                    echo 'label-success';
                } else {
                    echo 'label-danger';
                } ?>"><?php echo $summary['php']['status']; ?>
                </span>
            </div>
            <div class="info-column-plugin">
                <div title="Versión de PHP instalada en el servidor"
                     class="label label-info">?
                </div>
            </div>
            <div class="info-column-plugin">
                <span class="highlight-text"> Versión: </span>
            </div>
            <div class="info-column-plugin">
                <span class="label" id="php_version">
                    <?php echo $summary['php']['version']; ?>
                </span>
            </div>

        </div>
        <hr>
        <h4 class="tbk_table_title">Extensiones PHP requeridas</h4>
        <table class="tbk_table_info">
            <caption style="display: none">Tabla con el resumen de las extensiones de PHP requeridas</caption>
            <tr>
                <th>Extensión</th>
                <th>Estado</th>
                <th class="tbk_table_td">Versión</th>
            </tr>
            <tr>
                <td style="font-weight:bold">json</td>
                <td>
                        <span class="label
                            <?php if ($summary['phpExtensions']['json']['status'] == 'OK') {
                            echo 'label-success';
                        } else {
                            echo 'label-danger';
                        } ?>">
                            <?php echo $summary['phpExtensions']['json']['status']; ?>
                        </span>
                </td>
                <td class="tbk_table_td">
                    <?php echo $summary['phpExtensions']['json']['version']; ?>
                </td>
            </tr>
            <tr>
                <td style="font-weight:bold">dom</td>
                <td>
                        <span class="label
                            <?php if ($summary['phpExtensions']['dom']['status'] == 'OK') {
                            echo 'label-success';
                        } else {
                            echo 'label-danger';
                        } ?>">
                            <?php echo $summary['phpExtensions']['dom']['status']; ?>
                        </span>
                </td>
                <td class="tbk_table_td">
                    <?php echo $summary['phpExtensions']['dom']['version']; ?>
                </td>
            </tr>
            <tr>
                <td style="font-weight:bold">curl</td>
                <td>
                        <span class="label
                        <?php if ($summary['phpExtensions']['curl']['status'] == 'OK') {
                            echo 'label-success';
                        } else {
                            echo 'label-danger';
                        } ?>">
                            <?php echo $summary['phpExtensions']['curl']['status']; ?>
                        </span>
                </td>
                <td class="tbk_table_td">
                    <?php echo $summary['phpExtensions']['curl']['version']; ?>
                </td>
            </tr>
        </table>
    </div>


</div>



