<?php

namespace Transbank\WooCommerce\WebpayRest\Helpers;

use DateTime;
use DateTimeZone;
use Transbank\Webpay\Options;
use Transbank\WooCommerce\WebpayRest\Models\Transaction;
use Transbank\WooCommerce\WebpayRest\Helpers\TbkResponseUtil;
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
        global $wpdb;

        $orderby = isset($_GET['orderby']) ? sanitize_sql_orderby($_GET['orderby']) : 'ID';
        $order = isset($_GET['order']) ? sanitize_sql_orderby($_GET['order']) : 'DESC';

        $paged = (empty($_GET['paged']) ||
            !is_numeric($_GET['paged']) ||
            $_GET['paged'] <= 0) ? 1 :  esc_sql($_GET['paged']);

        $perPage = 20;
        $offset = ($paged - 1) * $perPage;

        $totalItemsQuery = 'SELECT COUNT(*) FROM '.Transaction::getTableName();
        $itemsQuery = 'SELECT * FROM '.Transaction::getTableName().' ORDER BY %i '.$order.' LIMIT %d, %d';

        $totalItems = $wpdb->get_var($totalItemsQuery);
        $totalPages = ceil($totalItems / $perPage);

        $this->items = $wpdb->get_results($wpdb->prepare(
            $itemsQuery,
            [$orderby, (int)$offset, (int)$perPage]
        ));

        $this->set_pagination_args([
            'total_items' => $totalItems,
            'total_pages' => $totalPages,
            'per_page'    => $perPage,
        ]);

        $columns = $this->get_columns();
        $this->_column_headers = [$columns, [], $this->get_sortable_columns(), 'id'];
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

        return TbkResponseUtil::transactionDateToLocalDate($tbkResponse->transactionDate);
    }

    public function column_created_at($item)
    {
        $utcDate = new DateTime($item->created_at, new DateTimeZone(wc_timezone_string()));

        return $utcDate->format('d-m-Y H:i:s P');
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
