<div id="message" class="notice notice-error tbk-notice-container">
    <div class="tbk-notice">
        <div>
            <img src="<?= esc_url($tbkLogo) ?>" height="70px" alt="Transbank logo" />
        </div>

        <div>
            <p><?= $noticeDescription ?></p>
            <a class="button button-primary tbk-button-primary" href="<?= $actionButton['action'] ?>">
                <?= $actionButton['text'] ?>
            </a>
        </div>
    </div>
</div>
