<?php
$id = isset($id) ? (string) $id : 'message';
$type = isset($type) ? (string) $type : 'error';

$logoUrl = isset($logoUrl) ? (string) $logoUrl : '';
$logoAlt = isset($logoAlt) ? (string) $logoAlt : __('Transbank logo', 'transbank_wc_plugin');
$logoHeight = isset($logoHeight) ? (int) $logoHeight : 70;

$title = isset($title) ? (string) $title : '';
$description = isset($description) ? (string) $description : '';

$actionButton = isset($actionButton) && is_array($actionButton) ? $actionButton : [];
$actionHref = isset($actionButton['action']) ? (string) $actionButton['action'] : '';
$actionText = isset($actionButton['text']) ? (string) $actionButton['text'] : '';
$actionClass = isset($actionButton['class']) ? (string) $actionButton['class'] : 'button button-primary tbk-button-primary';

$noticeClass = 'notice tbk-notice-container';
if ($type === 'success') {
    $noticeClass .= ' notice-success';
} elseif ($type === 'warning') {
    $noticeClass .= ' notice-warning';
} elseif ($type === 'info') {
    $noticeClass .= ' notice-info';
} else {
    $noticeClass .= ' notice-error';
}

if (!empty($isDismissible)) {
    $noticeClass .= ' is-dismissible';
}
?>

<div id="<?= esc_attr($id) ?>" class="<?= esc_attr($noticeClass) ?>">
    <div class="tbk-notice">
        <?php if ($logoUrl !== ''): ?>
            <div>
                <img src="<?= esc_url($logoUrl) ?>" height="<?= esc_attr((string) $logoHeight) ?>" alt="<?= esc_attr($logoAlt) ?>" />
            </div>
        <?php endif; ?>

        <div>
            <?php if ($title !== ''): ?>
                <p><strong><?= esc_html($title) ?></strong></p>
            <?php endif; ?>

            <?php if ($description !== ''): ?>
                <p><?= esc_html($description) ?></p>
            <?php endif; ?>

            <?php if ($actionHref !== '' && $actionText !== ''): ?>
                <a
                    class="<?= esc_attr($actionClass) ?>"
                    href="<?= esc_url($actionHref) ?>"
                    aria-label="<?= esc_attr($actionText) ?>">
                    <?= esc_html($actionText) ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>
