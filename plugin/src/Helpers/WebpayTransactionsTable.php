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
            'plural' => 'transbank_transactions',
            'ajax' => false,
        ]);
    }

    public function get_columns()
    {
        return [
            'order_id' => __('Nº de orden'),
            'amount' => __('Monto'),
            'product' => __('Producto'),
            'status' => __('Estado'),
            'token' => __('Token'),
            'buy_order' => __('Orden de compra'),
            'environment' => __('Ambiente'),
            'transaction_date' => __('Fecha'),
            'last_refund_type' => __('Transacción anulada'),
            'detail_error' => __('Observaciones'),
        ];
    }

    public function get_sortable_columns()
    {
        return [
            'buy_order' => ['buy_order', false],
            'amount' => ['amount', false],
            'order_id' => ['order_id', false],
            'product' => ['product', false],
            'status' => ['status', false],
            'environment' => ['environment', false],
            'transaction_date' => ['transaction_date', false],
        ];
    }

    /**
     * Prepare the table with different parameters, pagination, columns and table elements.
     */
    public function prepare_items()
    {
        global $wpdb;
        $orderByColumns = $this->get_sortable_columns();
        $orderby = isset($_GET['orderby']) && array_key_exists($_GET['orderby'], $orderByColumns)
            ? esc_sql($_GET['orderby'])
            : 'order_id';

        $order = isset($_GET['order']) && in_array(strtoupper($_GET['order']), ['ASC', 'DESC'])
            ? esc_sql(strtoupper($_GET['order']))
            : 'DESC';

        $paged = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
        $paged = $paged > 0 ? $paged : 1;

        $perPage = 20;
        $offset = ($paged - 1) * $perPage;

        $totalItemsQuery = 'SELECT COUNT(*) FROM ' . esc_sql(Transaction::getTableName());
        $totalItems = $wpdb->get_var($totalItemsQuery);

        $totalPages = ceil($totalItems / $perPage);

        $itemsQuery = "SELECT * FROM " . esc_sql(Transaction::getTableName()) . "
                   ORDER BY %i {$order}
                   LIMIT %d, %d";

        $this->items = $wpdb->get_results($wpdb->prepare(
            $itemsQuery,
            [$orderby, (int) $offset, (int) $perPage]
        ));

        $this->set_pagination_args([
            'total_items' => $totalItems,
            'total_pages' => $totalPages,
            'per_page' => $perPage,
        ]);

        $columns = $this->get_columns();
        $this->_column_headers = [$columns, [], $this->get_sortable_columns(), 'id'];
    }

    public function column_amount($item)
    {
        return '$' . number_format($item->amount, 0, ',', '.');
    }

    public function column_transaction_date($item)
    {
        if (!$item->transbank_response) {
            return '-';
        }
        $tbkResponse = json_decode($item->transbank_response);

        return TbkResponseUtil::transactionDateToLocalDate($tbkResponse->transactionDate);
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

        return '<a href="" onclick="this.innerHTML=\'' . $item->token . '\';return false; " title="Haz click para ver el token completo">...' . substr($item->token, -5) . '</a>';
    }

    public function column_status($item)
    {
        $statusDictionary = [
            Transaction::STATUS_PREPARED => 'Preparada',
            Transaction::STATUS_INITIALIZED => 'Inicializada',
            Transaction::STATUS_APPROVED => 'Aprobada',
            Transaction::STATUS_TIMEOUT => 'Timeout en formulario de pago',
            Transaction::STATUS_ABORTED_BY_USER => 'Abortada por el usuario',
            Transaction::STATUS_FAILED => 'Fallida',
        ];

        return $statusDictionary[$item->status] ?? $item->status;
    }

    public function column_order_id($item)
    {
        $userCanEditOrders = current_user_can('edit_shop_order', $item->order_id);

        if ($userCanEditOrders) {
            return '<a href="' . esc_url(admin_url('post.php?post=' . $item->order_id . '&action=edit')) . '" target="_blank">' . $item->order_id . '</a>';
        }

        return $item->order_id;
    }

    public function column_default($item, $column_name)
    {
        return $item->$column_name ?? '-';
    }
}
