<?php

namespace Transbank\WooCommerce\WebpayRest\Admin\ListTable;

use Transbank\Webpay\Options;
use Transbank\WooCommerce\WebpayRest\Helpers\TbkFactory;
use Transbank\Plugin\Helpers\ExitHelper;
use WP_List_Table;

class OneclickInscriptionsTable extends WP_List_Table
{
    private const PER_PAGE_DEFAULT = 15;
    private const PER_PAGE_OPTIONS = [10, 15, 20, 50, 100];
    /** @var string */
    private $environment;
    /** @var \Transbank\Plugin\Helpers\PluginLogger */
    private $logger;

    public function __construct()
    {
        parent::__construct([
            'singular' => 'transbank_inscription',
            'plural' => 'transbank_inscriptions',
            'ajax' => false,
        ]);

        $config = TbkFactory::getOneclickConfig();
        $this->environment = $config->getEnvironment();
        $this->logger = TbkFactory::createOneclickLogger();
    }

    public function get_columns()
    {
        return [
            'user_id' => __('ID de usuario'),
            'user' => __('Nombre de usuario'),
            'username' => __('Nombre de usuario Oneclick'),
            'email' => __('Correo electrónico'),
            'card_type' => __('Tipo de tarjeta'),
            'card_number' => __('Número de tarjeta'),
            'environment' => __('Ambiente'),
            'created_at' => __('Fecha de creación'),
            'actions' => __('Acciones'),
        ];
    }

    public function get_sortable_columns()
    {
        return [];
    }

    public function prepare_items()
    {
        $this->process_actions();

        $paged = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
        $paged = $paged > 0 ? $paged : 1;

        $perPage = isset($_GET['per_page']) ? absint($_GET['per_page']) : self::PER_PAGE_DEFAULT;
        if (!in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = self::PER_PAGE_DEFAULT;
        }
        $offset = ($paged - 1) * $perPage;

        $repository = TbkFactory::createInscriptionRepository();
        $totalItems = $repository->countFinishedByEnvironment($this->environment);
        $totalPages = (int) ceil($totalItems / $perPage);

        $this->items = $repository->listFinishedByEnvironment($this->environment, $offset, $perPage);

        $this->set_pagination_args([
            'total_items' => $totalItems,
            'total_pages' => $totalPages,
            'per_page' => $perPage,
        ]);

        $columns = $this->get_columns();
        $this->_column_headers = [$columns, [], [], 'email'];
    }

    public function no_items()
    {
        esc_html_e('No hay inscripciones Oneclick registradas.', 'transbank_wc_plugin');
    }

    public function column_default($item, $column_name)
    {
        $value = $item->$column_name ?? '-';
        return esc_html((string) $value);
    }

    public function column_username($item)
    {
        if (empty($item->username)) {
            return '<em>Usuario eliminado</em>';
        }

        return esc_html((string) $item->username);
    }

    public function column_environment($item)
    {
        if (($item->environment ?? null) === Options::ENVIRONMENT_INTEGRATION) {
            return 'Integración';
        }

        return 'Producción';
    }

    public function column_actions($item)
    {
        $id = isset($item->id) ? (int) $item->id : 0;
        if ($id <= 0) {
            return '-';
        }

        $deleteUrl = wp_nonce_url(
            add_query_arg([
                'page' => 'transbank_webpay_plus_rest',
                'tbk_tab' => 'inscriptions',
                'action' => 'delete',
                'inscription_id' => $id,
            ], admin_url('admin.php')),
            'tbk_delete_inscription_' . $id
        );

        return sprintf(
            '<a href="%s" class="button tbk-button-danger tbk-js-delete-inscription">
                        <i class="fas fa-trash" aria-hidden="true"></i>
                        <span>Eliminar</span>
                    </a>',
            esc_url($deleteUrl)
        );
    }

    private function process_actions(): void
    {
        $action = $this->current_action();
        if ($action !== 'delete') {
            return;
        }

        if (!current_user_can('manage_woocommerce')) {
            wp_die('No autorizado');
        }

        $id = isset($_GET['inscription_id']) ? (int) $_GET['inscription_id'] : 0;
        if ($id <= 0) {
            return;
        }

        $nonce = isset($_GET['_wpnonce']) ? (string) $_GET['_wpnonce'] : '';
        if (!wp_verify_nonce($nonce, 'tbk_delete_inscription_' . $id)) {
            wp_die('Nonce inválido');
        }

        try {
            $this->logger->logInfo('Eliminando inscripción Oneclick', ['inscription_id' => $id]);
            $oneclickService = TbkFactory::createOneclickInscriptionService();
            $oneclickService->deleteByInscriptionId($id);

            $this->logger->logInfo('Inscripción Oneclick eliminada correctamente', ['inscription_id' => $id]);

            $this->redirectWithNotice('success', 'Inscripción eliminada correctamente');
        } catch (\Exception $e) {
            $this->logger->logError('Error al eliminar inscripción Oneclick', ['inscription_id' => $id, 'error' => $e->getMessage()]);
            $noticeMessage = $e->getMessage() === 'Inscripción no encontrada.'
                || $e->getMessage() === 'Payment token no encontrado para eliminar.'
                ? 'Inscripción no encontrada.'
                : 'Error al eliminar la inscripción.';
            $this->redirectWithNotice('error', $noticeMessage);
        }
    }

    private function redirectWithNotice(string $type, string $message): void
    {
        $url = remove_query_arg(['action', 'inscription_id', '_wpnonce']);

        $url = add_query_arg([
            'tbk_notice_id' => 'tbk-inscription-delete-notice',
            'tbk_notice_type' => $type,
            'tbk_notice_msg' => rawurlencode($message),
        ], $url);

        wp_safe_redirect($url);
        ExitHelper::terminate();
    }
}
