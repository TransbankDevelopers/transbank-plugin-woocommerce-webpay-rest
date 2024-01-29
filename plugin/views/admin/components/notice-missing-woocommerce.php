<div id="message" class="notice notice-error tbk-notice-container">
    <div class="tbk-notice">
        <div>
            <img src="<?= esc_url($tbkLogo) ?>" height="70px" alt="Transbank logo" />
        </div>

        <div>
            <p>WooCommerce no se encuentra activo o no está instalado.</p>
            <a class="button button-primary tbk-button-primary" href="<?= $actionButton['action'] ?>">
                <?= $actionButton['text'] ?>
            </a>
        </div>
    </div>
</div>
