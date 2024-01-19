<?php

namespace Transbank\WooCommerce\WebpayRest\Helpers;

use DateTime;
use DateTimeZone;
use Transbank\Webpay\Options;
use Transbank\WooCommerce\WebpayRest\Models\Transaction;
use WP_List_Table;

class WebpayTransactionsTable extends WP_List_Table
{
    /**
     * Constructor, we override the parent to pass our own arguments
     * We usually focus on three parameters: singular and plural labels, as well as whether the class supports AJAX.
     */
    public function __construct()
    {
        parent::__construct([
            'singular' => 'transbank_transaction',
            'plural'   => 'transbank_transactions',
            'ajax'     => false,
        ]);
    }

    public function get_columns()
    {
        return [
            'id'               => __('ID'),
            'product'          => __('Producto'),
            'order_id'         => __('Orden WooCommerce'),
            'status'           => __('Estado interno'),
            'transbank_status' => __('Estado Transacción'),
            'buy_order'        => __('Orden Compra Transbank'),
            'token'            => __('Token'),
            'environment'      => __('Ambiente'),
            'amount'           => __('Monto'),
            'created_at'       => __('Fecha creación'),
            'transaction_date' => __('Fecha Transacción Transbank'),
            'last_refund_type' => __('Último refund'),
            'error' => __('Error'),
            'detail_error' => __('Detalle de Error'),
        ];
    }

    public function get_sortable_columns()
    {
        return [
            'id'          => ['id', true],
            'buy_order'   => ['buy_order', false],
            'amount'      => ['amount', false],
            'order_id'    => ['order_id', false],
            'product'     => ['product', false],
            'status'      => ['status', false],
            'environment' => ['environment', false],
            'created_at'  => ['created_at', false],
        ];
    }

    /**
     * Prepare the table with different parameters, pagination, columns and table elements.
     */
    public function prepare_items()
    {
        global $wpdb, $_wp_column_headers;
        $screen = get_current_screen();

        $query = 'SELECT * FROM '.Transaction::getTableName();

        $orderby = isset($_GET['orderby']) ? sanitize_sql_orderby($_GET['orderby']) : 'ID';
        $order = isset($_GET['order']) ? sanitize_sql_orderby($_GET['order']) : 'DESC';
        $query .= ' ORDER BY %s %s';

        $totalItems = $wpdb->query($wpdb->prepare(
            $query,
            [$orderby, $order]
        ));

        $perPage = 20;

        $paged = !empty($_GET['paged']) ? esc_sql($_GET['paged']) : '';

        if (empty($paged) || !is_numeric($paged) || $paged <= 0) {
            $paged = 1;
        }
        $totalPages = ceil($totalItems / $perPage);

        if (!empty($paged) && !empty($perPage)) {
            $offset = ($paged - 1) * $perPage;
            $query .= ' LIMIT %d, %d';
        }

        $this->set_pagination_args([
            'total_items' => $totalItems,
            'total_pages' => $totalPages,
            'per_page'    => $perPage,
        ]);

        $columns = $this->get_columns();
        $_wp_column_headers[$screen->id] = $columns;
        $this->_column_headers = [$columns, [], $this->get_sortable_columns(), 'id'];

        $this->items = $wpdb->get_results($wpdb->prepare(
            $query,
            [$orderby, $order, (int)$offset, (int)$perPage]
        ));
    }

    public function column_amount($item)
    {
        return '$'.number_format($item->amount, 0, ',', '.');
    }

    public function column_transaction_date($item)
    {
        if (!$item->transbank_response) {
            return '-';
        }
        $tbkResponse = json_decode($item->transbank_response);

        $utcDate = new DateTime($tbkResponse->transactionDate, new DateTimeZone('UTC'));
        $utcDate->setTimeZone(new DateTimeZone(wc_timezone_string()));

        return $utcDate->format('d-m-Y H:i:s');
    }

    public function column_created_at($item)
    {
        $utcDate = new DateTime($item->created_at, new DateTimeZone('UTC'));
        $utcDate->setTimeZone(new DateTimeZone(wc_timezone_string()));

        return $utcDate->format('d-m-Y H:i:s');
    }

    public function column_environment($item)
    {
        if ($item->environment === Options::ENVIRONMENT_INTEGRATION) {
            return 'Integración';
        }

        return 'Producción';
    }

    public function column_product($item)
    {
        if ($item->product === Transaction::PRODUCT_WEBPAY_ONECLICK) {
            return 'Webpay Oneclick';
        }

        return 'Webpay Plus';
    }

    public function column_token($item)
    {
        if ($item->product === Transaction::PRODUCT_WEBPAY_ONECLICK) {
            return '-';
        }

        return '<a href="" onclick="this.innerHTML=\''.$item->token.'\';return false; " title="Haz click para ver el token completo">...'.substr($item->token, -5).'</a>';
    }

    public function column_default($item, $column_name)
    {
        return $item->$column_name ?? '-';
    }
}
