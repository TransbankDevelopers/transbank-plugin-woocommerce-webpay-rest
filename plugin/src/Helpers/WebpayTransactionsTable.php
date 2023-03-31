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
            'singular' => 'transbank_transaction', //Singular label
            'plural'   => 'transbank_transactions', //plural label, also this well be one of the table css class
            'ajax'     => false, //We won't support Ajax for this table
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

        /* -- Preparing your query -- */
        $query = 'SELECT * FROM '.Transaction::getTableName();

        /* -- Ordering parameters -- */
        //Parameters that are going to be used to order the result
        $orderby = isset($_GET['orderby']) ? sanitize_sql_orderby($_GET['orderby']) : 'ID';
        $order = isset($_GET['order']) ? sanitize_sql_orderby($_GET['order']) : 'DESC';
        $query .= ' ORDER BY '.$orderby.' '.$order;
        /* -- Pagination parameters -- */
        //Number of elements in your table?
        $totalitems = $wpdb->query($wpdb->prepare($query)); //return the total number of affected rows
        //How many to display per page?
        $perpage = 20;
        //Which page is this?
        $paged = !empty($_GET['paged']) ? esc_sql($_GET['paged']) : '';
        //Page Number
        if (empty($paged) || !is_numeric($paged) || $paged <= 0) {
            $paged = 1;
        } //How many pages do we have in total?
        $totalpages = ceil($totalitems / $perpage); //adjust the query to take pagination into account
        if (!empty($paged) && !empty($perpage)) {
            $offset = ($paged - 1) * $perpage;
            $query .= ' LIMIT '.(int) $offset.','.(int) $perpage;
        } /* -- Register the pagination -- */
        $this->set_pagination_args([
            'total_items' => $totalitems,
            'total_pages' => $totalpages,
            'per_page'    => $perpage,
        ]);
        //The pagination links are automatically built according to those parameters

        /* -- Register the Columns -- */
        $columns = $this->get_columns();
        $_wp_column_headers[$screen->id] = $columns;
        $this->_column_headers = [$columns, [], $this->get_sortable_columns(), 'id'];

        /* -- Fetch the items -- */
        $this->items = $wpdb->get_results($wpdb->prepare($query));
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
