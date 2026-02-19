<?php

namespace Transbank\WooCommerce\WebpayRest\Admin\Notices;

use Transbank\WooCommerce\WebpayRest\Config\TransbankPluginSettings;

class ReviewNotice implements NoticeInterface
{
    public const NOTICE_ID = 'tbk-review-notice';

    private NoticeRenderer $renderer;
    private TransbankPluginSettings $settings;

    public function __construct(NoticeRenderer $renderer, TransbankPluginSettings $settings)
    {
        $this->renderer = $renderer;
        $this->settings = $settings;
    }

    public function shouldRender(): bool
    {
        if ($this->settings->isReviewNoticeDismissed()) {
            return false;
        }

        $page = (string) ($_GET['page'] ?? '');
        $section = (string) ($_GET['section'] ?? '');

        return $page === 'wc-settings' && $section !== '' && strpos($section, 'transbank') !== false;
    }

    public function renderNotice(): void
    {
        $this->renderer->display([
            'id' => self::NOTICE_ID,
            'type' => 'info',
            'title' => 'Tu opinión es importante para nosotros',
            'titleClass' => 'tbk-notice-title',
            'description' => '¿Podrías tomarte un momento para dejarnos una reseña en el repositorio de WordPress? Solo te tomará un par de minutos y nos ayudará a seguir mejorando y llegar a más personas como tú.',
            'logoHeight' => 70,
            'isDismissible' => true,
            'contentClass' => 'tbk-notice-content',
            'actionButton' => [
                'text' => 'Dejar reseña',
                'action' => 'https://wordpress.org/support/plugin/transbank-webpay-plus-rest/reviews/#new-post',
                'class' => 'button button-primary tbk-button-primary',
                'attrs' => [
                    'target' => '_blank',
                    'rel' => 'noopener',
                ],
            ],
        ]);
    }
}
