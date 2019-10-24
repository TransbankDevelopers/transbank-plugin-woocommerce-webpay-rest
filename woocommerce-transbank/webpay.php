<?php
if (!defined('ABSPATH')) {
    exit();
} // Exit if accessed directly

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @wordpress-plugin
 * Plugin Name: Transbank Webpay Plus Rest
 * Plugin URI: https://www.transbankdevelopers.cl/plugin/woocommerce/webpay
 * Description: Recibe pagos en l&iacute;nea con Tarjetas de Cr&eacute;dito y Redcompra en tu WooCommerce a trav&eacute;s de Webpay Plus.
 * Version: 1.0.0
 * Author: Transbank
 * Author URI: https://www.transbank.cl
 * WC requires at least: 3.4.0
 * WC tested up to: 3.5.4
 */

add_action('plugins_loaded', 'woocommerce_transbank_init', 0);

require_once ABSPATH . "wp-includes/pluggable.php";
require_once plugin_dir_path( __FILE__ ) . "vendor/autoload.php";
require_once plugin_dir_path( __FILE__ ) . "libwebpay/HealthCheck.php";
require_once plugin_dir_path( __FILE__ ) . "libwebpay/LogHandler.php";
require_once plugin_dir_path( __FILE__ ) . "libwebpay/TransbankSdkWebpayRest.php";

function woocommerce_transbank_init() {

    if (!class_exists("WC_Payment_Gateway")) {
        return;
    }

    class WC_Gateway_Transbank extends WC_Payment_Gateway {

        private static $URL_RETURN;
        private static $URL_FINAL;

        var $notify_url;
        var $plugin_url;

        public function __construct() {

            self::$URL_RETURN = home_url('/') . '?wc-api=WC_Gateway_transbank';
            self::$URL_FINAL = '_URL_';

            $this->id = 'transbank';
            $this->icon = "https://www.transbank.cl/public/img/Logo_Webpay3-01-50x50.png";
            $this->method_title = __('Transbank Webpay Plus Rest');
            $this->notify_url = add_query_arg('wc-api', 'WC_Gateway_' . $this->id, home_url('/'));
            $this->title = 'Transbank Webpay Rest';
            $this->description = 'Permite el pago de productos y/o servicios, con Tarjetas de Cr&eacute;dito y Redcompra a trav&eacute;s de Webpay Plus Rest';
            $this->plugin_url = plugins_url('/', __FILE__);
            $this->log = new LogHandler();

            $keys = include 'libwebpay/IntegrationKeys.php';
            $webpay_commerce_code = $keys['commerce_code'];
            $webpay_api_key = $keys['private_key'];


            $this->config = array(
                "MODO" => trim($this->get_option('webpay_test_mode', 'TEST')),
                "COMMERCE_CODE" => $this->get_option('webpay_commerce_code', $webpay_commerce_code),
                "API_KEY" => $this->get_option('webpay_api_key', $webpay_api_key),
                "URL_RETURN" => home_url('/') . '?wc-api=WC_Gateway_' . $this->id,
                "ECOMMERCE" => 'woocommerce',
                "VENTA_DESC" => array(
                    "VD" => "Venta Deb&iacute;to",
                    "VN" => "Venta Normal",
                    "VC" => "Venta en cuotas",
                    "SI" => "3 cuotas sin inter&eacute;s",
                    "S2" => "2 cuotas sin inter&eacute;s",
                    "NC" => "N cuotas sin inter&eacute;s"
                )
            );

            /**
             * Carga configuración y variables de inicio
             **/

            $this->init_form_fields();
            $this->init_settings();

            add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_api_wc_gateway_' . $this->id, array($this, 'check_ipn_response'));

            if (!$this->is_valid_for_use()) {
                $this->enabled = false;
            }
        }

        /**
         * Comprueba configuración de moneda (Peso Chileno)
         **/
        function is_valid_for_use() {
            if (!in_array(get_woocommerce_currency(),
                apply_filters('woocommerce_' . $this->id . '_supported_currencies', array('CLP')))) {
                return false;
            }
            return true;
        }

        /**
         * Inicializar campos de formulario
         **/
        function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Activar/Desactivar', 'woocommerce'),
                    'type' => 'checkbox',
                    'default' => 'yes'
                ),
                'webpay_test_mode' => array(
                    'title' => __('Ambiente', 'woocommerce'),
                    'type' => 'select',
                    'options' => array(
                        'TEST' => 'Integraci&oacute;n',
                        'LIVE' => 'Producci&oacute;n'
                    ),
                    'default' => __('TEST', 'woocommerce')
                ),
                'webpay_commerce_code' => array(
                    'title' => __('C&oacute;digo de Comercio', 'woocommerce'),
                    'type' => 'text',
                    'default' => __($this->config['COMMERCE_CODE'], 'woocommerce')
                ),
                'webpay_api_key' => array(
                    'title' => __('API Key', 'woocommerce'),
                    'type' => 'text',
                    'default' => __($this->config['API_KEY'], 'woocommerce')
                )
            );
        }

        /**
         * Pagina Receptora
         **/
        function receipt_page($order_id) {

            $order = new WC_Order($order_id);
            $amount = (int) number_format($order->get_total(), 0, ',', '');
            $sessionId = uniqid();
            $buyOrder = $order_id;
            $returnUrl = self::$URL_RETURN;

            $returnUrl = $returnUrl . '&orid=' . $order_id;


            $transbankSdkWebpay = new TransbankSdkWebpayRest($this->config);
            $result = $transbankSdkWebpay->createTransaction($amount, $sessionId, $buyOrder, $returnUrl);

            if (isset($result["token_ws"])) {

                $url = $result["url"];
                $token_ws = $result["token_ws"];

                self::redirect($url, array("token_ws" => $token_ws));
                exit;

            } else {
                wc_add_notice( __('ERROR: ', 'woothemes') .
                    'Ocurri&oacute; un error al intentar conectar con WebPay Plus. Por favor intenta mas tarde.<br/>',
                    'error');
            }
        }

        /**
         * Obtiene respuesta IPN (Instant Payment Notification)
         **/
        function check_ipn_response() {
            @ob_clean();
            if (isset($_POST)) {
                header('HTTP/1.1 200 OK');
                $this->check_ipn_request_is_valid($_POST);
            } else {
                echo "Ocurrio un error al procesar su Compra";
            }
        }

        /**
         * Valida respuesta IPN (Instant Payment Notification)
         **/
        public function check_ipn_request_is_valid($data) {

            $token_ws = isset($data["token_ws"]) ? $data["token_ws"] : null;

            $transbankSdkWebpay = new TransbankSdkWebpayRest($this->config);
            $result = $transbankSdkWebpay->commitTransaction($token_ws);

            $order_id = $_GET['orid'];
            $order_info = new WC_Order($order_id);

            if (is_object($result) && isset($result->buyOrder)) {

                WC()->session->set($order_info->get_order_key(), $result);

                if ($result->responseCode == 0) {

                    WC()->session->set($order_info->get_order_key() . "_transaction_paid", 1);

                    $order_info->add_order_note(__('Pago exitoso con Webpay Plus Rest', 'woocommerce'));
                    $order_info->add_order_note(__(json_encode($result), 'woocommerce'));
                    $order_info->update_status('processing');
                    wc_reduce_stock_levels($order_id);
                    $finalUrl = str_replace("_URL_",
                        add_query_arg( 'key', $order_info->get_order_key(), $order_info->get_checkout_order_received_url()), self::$URL_FINAL);

                    $finalUrl = $finalUrl . '&orid=' . $order_id;
                    self::redirect($finalUrl, array("token_ws" => $token_ws));
                    die();
                }
            }

            $order_info = new WC_Order($order_id);
            if ($order_info->has_status( 'pending')) {
                $order_info->add_order_note(__('Pago rechazado con Webpay Plus Rest', 'woocommerce'));
                $order_info->add_order_note(__(json_encode($result), 'woocommerce'));
                $order_info->update_status('failed');
            }
            $error_message = "Estimado cliente, le informamos que su orden termin&oacute; de forma inesperada";
            wc_add_notice(__('ERROR: ', 'woothemes') . $error_message, 'error');

            self::redirect($order_info->get_checkout_payment_url(), array("token_ws" => $token_ws));
            die();
        }

        /**
         * Generar pago en Transbank
         **/

        public function redirect($url, $data) {
            echo "<form action='" . $url . "' method='POST' name='webpayForm'>";
            foreach ($data as $name => $value) {
                echo "<input type='hidden' name='" .htmlentities($name) . "' value='" . htmlentities($value) . "'>";
            }
            echo "</form>" .
                "<script language='JavaScript'>" .
                "document.webpayForm.submit();" .
                "</script>";
        }

        /**
         * Procesar pago y retornar resultado
         **/
        function process_payment($order_id) {
            $order = new WC_Order($order_id);
            return array(
                'result' => 'success',
                'redirect' => $order->get_checkout_payment_url(true)
            );
        }

        /**
         * Opciones panel de administración
         **/
        public function admin_options() {

            $this->healthcheck = new HealthCheck($this->config);
            $datos_hc = json_decode($this->healthcheck->printFullResume());
            ?>
            <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">

			<link href="<?php echo plugin_dir_path( __FILE__ ) ?>css/bootstrap-switch.css" rel="stylesheet">
			<link href="<?php echo plugin_dir_path( __FILE__ ) ?>ccss/tbk.css" rel="stylesheet">

			<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
			<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
			<script src="https://unpkg.com/bootstrap-switch"></script>

            <h3><?php _e('Transbank Webpay', 'woocommerce'); ?></h3>
            <p><?php _e('Transbank es la empresa l&iacute;der en negocios de medio de pago seguros en Chile.'); ?></p>

			<a class ="tbk_btn tbk_danger_btn" data-toggle="modal" href="#tb_modal">Informacion</a>
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
                                            <script> $("[name=\'action_check\']").bootstrapSwitch();</script>
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
                                                Actualizar Parametros
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
			<script type="text/javascript">
				function updateConfig(){
                }

                $(".check_conn").click(function(e) {

                    $(".check_conn").text("Verificando ...");
                    $("#row_response_status").hide();
                    $("#row_response_url").hide();
                    $("#row_response_token").hide();
                    $("#row_error_message").hide();
                    $("#row_error_detail").hide();
                    $(".tbk_table_trans").empty();

                    $.post('../wp-content/plugins/<?php echo plugin_basename( __DIR__ )?>/libwebpay/CheckConn.php', {}, function(response){

                        $(".check_conn").text("Verificar Conexión");
                        $("#row_response_status").show();
                        $("#row_response_status_text").removeClass("label-success").removeClass("label-danger");

                        if(response.status.string == "OK") {

                            $("#row_response_status_text").addClass("label-success").text("OK").show();
                            $("#row_response_url_text").append(response.response.url);
                            $("#row_response_token_text").append('<pre>'+response.response.token_ws+'</pre>');

                            $("#row_response_url").show();
                            $("#row_response_token").show();

                        } else {

                            $("#row_response_status_text").addClass("label-danger").text("ERROR").show();
                            $("#row_error_message_text").append(response.response.error);
                            $("#row_error_detail_text").append('<pre>'+response.response.detail+'</pre>');

                            $("#row_error_message").show();
                            $("#row_error_detail").show();
                        }

                    },'json');

                    e.preventDefault();
                });
			</script>
			<?php
        }
    }

    /**
     * Añadir Transbank Plus a Woocommerce
     **/
    function woocommerce_add_transbank_gateway($methods) {
        $methods[] = 'WC_Gateway_transbank';
        return $methods;
    }

    /**
     * Muestra detalle de pago a Cliente a finalizar compra
     **/
    function pay_content($order_id) {
        $order_info = new WC_Order($order_id);
        $transbank_data = new WC_Gateway_transbank();

        if ($order_info->get_payment_method_title() == $transbank_data->title) {
            if (WC()->session->get($order_info->get_order_key() . "_transaction_paid") == "" &&
                WC()->session->get($order_info->get_order_key()) == "" && $order_info->has_status( 'pending')) {

                $order_info->add_order_note(__('Pago cancelado con Webpay Plus Rest', 'woocommerce'));
                $order_info->update_status('failed');

                wc_add_notice(__('Compra <strong>Anulada</strong>', 'woocommerce') .
                        ' por usuario. Recuerda que puedes pagar o cancelar tu compra cuando lo desees desde <a href="' .
                        wc_get_page_permalink('myaccount') . '">' . __('Tu Cuenta', 'woocommerce') . '</a>',
                    'error'
                );
                wp_redirect($order_info->get_checkout_payment_url());
                die();
            }
        } else {
            return;
        }

        $finalResponse = WC()->session->get($order_info->get_order_key());
        WC()->session->set($order_info->get_order_key(), "");

        $paymentTypeCode = $finalResponse->paymentTypeCode;
        $paymentCodeResult = $transbank_data->config['VENTA_DESC'][$paymentTypeCode];

        if ($finalResponse->responseCode == 0) {
            $transactionResponse = "Transacci&oacute;n Aprobada";
        } else {
            $transactionResponse = "Transacci&oacute;n Rechazada";
        }

        $date_accepted = new DateTime($finalResponse->transactionDate);

        if ($finalResponse != null) {

            if($paymentTypeCode == "SI" || $paymentTypeCode == "S2" ||
                $paymentTypeCode == "NC" || $paymentTypeCode == "VC" ) {
                $installmentType = $paymentCodeResult;
            } else {
                $installmentType = "Sin cuotas";
            }

            if($paymentTypeCode == "VD"){
                $paymentType = "Débito";
            } else {
                $paymentType = "Crédito";
            }

            update_post_meta($order_id, 'transactionResponse', $transactionResponse);
            update_post_meta($order_id, 'buyOrder', $finalResponse->buyOrder);
            update_post_meta($order_id, 'authorizationCode', $finalResponse->authorizationCode);
            update_post_meta($order_id, 'cardNumber', $finalResponse->cardDetail);
            update_post_meta($order_id, 'paymenCodeResult', $paymentCodeResult);
            update_post_meta($order_id, 'amount', $finalResponse->amount);
            update_post_meta($order_id, 'coutas', $finalResponse->installmentsNumber);
            update_post_meta($order_id, 'transactionDate', $date_accepted->format('d-m-Y / H:i:s') );

            echo '</br><h2>Detalles del pago</h2>' .
                    '<table class="shop_table order_details">' .
                    '<tfoot>' .
                    '<tr>' .
                    '<th scope="row">Respuesta de la Transacci&oacute;n:</th>' .
                    '<td><span class="RT">' .
                    $transactionResponse .
                    '</span></td>' .
                    '</tr>' .
                    '<tr>' .
                    '<th scope="row">C&oacute;digo de la Transacci&oacute;n:</th>' .
                    '<td><span class="CT">' .
                    $finalResponse->responseCode .
                    '</span></td>' .
                    '</tr>' .
                    '<tr>' .
                    '<th scope="row">Orden de Compra:</th>' .
                    '<td><span class="RT">' .
                    $finalResponse->buyOrder .
                    '</span></td>' .
                    '</tr>' .
                    '<tr>' .
                    '<th scope="row">Codigo de Autorizaci&oacute;n:</th>' .
                    '<td><span class="CA">' .
                    $finalResponse->authorizationCode .
                    '</span></td>' .
                    '</tr>' .
                    '<tr>' .
                    '<th scope="row">Fecha Transacci&oacute;n:</th>' .
                    '<td><span class="FC">' .
                    $date_accepted->format('d-m-Y') .
                    '</span></td>' .
                    '</tr>' .
                    '<tr>' .
                    '<th scope="row"> Hora Transacci&oacute;n:</th>' .
                    '<td><span class="FT">' .
                    $date_accepted->format('H:i:s') .
                    '</span></td>' .
                    '</tr>' .
                    '<tr>' .
                    '<th scope="row">Tarjeta de Cr&eacute;dito:</th>' .
                    '<td><span class="TC">************' .
                     $finalResponse->cardDetail['card_number'] .
                    '</span></td>' .
                    '</tr>' .
                    '<tr>' .
                    '<th scope="row">Tipo de Pago:</th>' .
                    '<td><span class="TP">' .
                    $paymentType .
                    '</span></td>' .
                    '</tr>' .
                    '<tr>' .
                    '<th scope="row">Tipo de Cuota:</th>' .
                    '<td><span class="TC">' .
                    $installmentType .
                    '</span></td>' .
                    '</tr>' .
                    '<tr>' .
                    '<th scope="row">Monto Compra:</th>' .
                    '<td><span class="amount">' .
                    $finalResponse->amount .
                    '</span></td>' .
                    '</tr>' .
                    '<tr>' .
                    '<th scope="row">N&uacute;mero de Cuotas:</th>' .
                    '<td><span class="NC">' .
                    $finalResponse->installmentsNumber .
                    '</span></td>' .
                    '</tr>' .
                    '</tfoot>' .
                    '</table><br/>';
        }
    }

    add_action('woocommerce_thankyou', 'pay_content', 1);
    add_filter('woocommerce_payment_gateways','woocommerce_add_transbank_gateway');

    add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'add_action_links' );

    function add_action_links ( $links ) {
        $newLinks = array(
            '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=transbank' ) . '">Settings</a>',
        );
        return array_merge( $links, $newLinks );
    }
}
