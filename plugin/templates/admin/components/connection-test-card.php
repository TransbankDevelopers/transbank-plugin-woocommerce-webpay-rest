<?php

if (!defined('ABSPATH')) {
    return;
}

$productKey = $connectionTest['productKey'] ?? '';
$actionName = $connectionTest['actionName'] ?? 'check_connection';
$title = $connectionTest['title'] ?? '';
$description = $connectionTest['description'] ?? '';
$initialStatusText = $connectionTest['initialStatusText'] ?? 'No ejecutado';
$initialEnvironmentText = $connectionTest['initialEnvironmentText'] ?? 'No ejecutado';
?>

<div
    class="tbk-diagnostic-card"
    data-connection-test="<?php echo esc_attr($productKey); ?>"
    data-action="<?php echo esc_attr($actionName); ?>"
    data-product-key="<?php echo esc_attr($productKey); ?>">
    <div class="tbk-diagnostic-card-header">
        <div class="tbk-diagnostic-card-top-row">
            <h4 class="tbk-diagnostic-card-title"><?php echo esc_html($title); ?></h4>
            <div class="tbk-diagnostic-actions">
                <button class="button tbk-button-primary" data-role="check-button">Verificar conexión</button>
            </div>
        </div>
        <p class="tbk-diagnostic-card-description"><?php echo esc_html($description); ?></p>
    </div>

    <div class="tbk-response-status" data-role="result-container">
        <div class="tbk-diagnostic-result-header">
            <div class="tbk-diagnostic-result-header-copy">
                <span class="tbk-diagnostic-result-kicker">Resultado de la prueba</span>
                <div class="tbk-diagnostic-loading" data-role="loading">
                    <i class="fa fa-spinner fa-spin" aria-hidden="true"></i>
                    <span>Verificando conexión...</span>
                </div>
            </div>
        </div>

        <div class="tbk-diagnostic-result-card">
            <div class="tbk-diagnostic-result-row">
                <span class="tbk-diagnostic-meta-label">Resultado</span>
                <span class="label" data-role="status-badge"><?php echo esc_html($initialStatusText); ?></span>
            </div>
            <div class="tbk-diagnostic-result-row">
                <span class="tbk-diagnostic-meta-label">Entorno</span>
                <span class="tbk-diagnostic-result-value" data-role="environment-value"><?php echo esc_html($initialEnvironmentText); ?></span>
            </div>
        </div>
    </div>
</div>
