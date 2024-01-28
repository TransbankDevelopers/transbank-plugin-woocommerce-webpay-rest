<div id="message" class="notice notice-error tbk_notice_container">
    <div class="tbk_notice">
        <div>
            <img src="<?= esc_url($tbkLogo) ?>" height="70px" alt="Transbank logo" />
        </div>

        <div>
            <p>WooCommerce no se encuentra activo o no está instalado.</p>
            <p>
                <a class="button button-primary tbk_button_primary" href="<?= $actionButton['action'] ?>">
                    <?= $actionButton['text'] ?>
                </a>
            </p>
        </div>
    </div>
</div>
