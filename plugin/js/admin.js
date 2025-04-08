jQuery(function($) {
    const hideElement = (name) => $(`#${name}`).addClass("tbk-hide");
    const showElement = (name) => $(`#${name}`).removeClass("tbk-hide");
    const post = (action, data, fnOk, fnError) => {
        $.post(ajax_object.ajax_url, {action, nonce: ajax_object.nonce, ...data}, function(resp){
            fnOk(resp);
        }).fail(function(error) {
            fnError(error);
        });
    }

    const blockButton = (e, btnName, msg) => {
        if ($(`.${btnName}`).data('sending') === true) {
            return false;
        }
        const btn = $(`.${btnName}`);
        btn.data('sending', true).html(`${msg} <i class="fa fa-spinner fa-spin"></i>`);

        e.preventDefault();
        return true;
    }

    const releaseButton = (btnName, msg) => {
        $(`.${btnName}`).data('sending', false).html(msg);
    }

    $(".check_conn").on("click",function(e) {
        if (!blockButton(e, 'check_conn', 'Verificando ...')){
            return;
        }
        showElement("tbk_response_status");
        hideElement("div_status_error");
        hideElement("div_status_ok");
        post('check_connection', {}, (resp) => {
            if(resp.status.string == "OK") {
                $("#response_url_text").text(resp.response.url);
                $("#response_token_text").html('<pre>'+resp.response.token+'</pre>');
                showElement("div_status_ok");
            } else {
                $("#error_response_text").text(resp.response.error);
                $("#error_detail_response_text").html('<code style="display: block; padding: 10px">'+resp.response.detail+'</code>');
                showElement("div_status_error");
            }
            releaseButton('check_conn',"Verificar Conexión");
        });
    });

    $(".check_exist_tables").on("click",function(e) {
        if (!blockButton(e, 'check_exist_tables', 'Verificando ...')){
            return;
        }
        showElement("tbk-tbl-response-title");
        showElement("tbl_response_status_text");
        hideElement("div_tables_status_result");
        hideElement("div_tables_error");

        post('check_exist_tables', {}, (resp) => {
            showElement("div_tables_status");
            if(!resp.error) {
                $("#tbl_response_status_text").addClass("label-success");
                $("#tbl_response_status_text").text("OK");
                $("#tbl_response_result_text").text(resp.msg);
                $("#tbl_response_status_text").show();
                showElement("div_tables_status_result");
            } else {
                $("#tbl_response_status_text").addClass("label-danger");
                $("#tbl_response_status_text").text("ERROR");
                $("#tbl_response_status_text").show();
                $("#tbl_error_message_text").html('<code style="display: block; padding: 10px">' + resp.error + ' Exception: ' + resp.exception +'</code>');
                showElement("div_tables_error");
            }
            releaseButton('check_exist_tables',"Verificar Tablas");
        });
    });

    $('.get-transaction-status').on("click",function(e) {
        e.preventDefault();
        if (!blockButton(e, 'get-transaction-status', 'Consultando estado')){
            return;
        }

        const container = document.getElementById('transaction_status_admin');
        container.innerHTML = '';

        const separator = document.createElement('div');
        separator.className = 'tbk-separator';
        separator.style.display = 'none';
        container.appendChild(separator);

        post('get_transaction_status', {
            order_id: $('.get-transaction-status').data('order-id'),
            buy_order: $('.get-transaction-status').data('buy-order'),
            token: $('.get-transaction-status').data('token')
        }, (resp) => {
            for (const [key, value] of Object.entries(resp)) {
                const fieldName = document.createElement('span');
                fieldName.className = 'tbk-field-name';
                fieldName.textContent = getFieldName(key);

                const fieldValue = document.createElement('span');
                fieldValue.className = 'tbk-field-value';
                fieldValue.textContent = value;

                if(key == 'status') {
                    fieldValue.classList.add('tbk-badge');
                    fieldValue.classList.add(getBadgeColorFromStatus(value));
                }

                if(key == 'cardNumber') {
                    fieldValue.style.width = '100%';
                }

                const field = document.createElement('div');
                field.className = 'tbk-field';
                field.appendChild(fieldName);
                field.appendChild(fieldValue);

                container.appendChild(field);
            }
            separator.style.removeProperty('display');

            releaseButton('get-transaction-status','Consultar Estado');
        }, (error) => {
            const errorContainer = createErrorContainer(error.responseJSON.message);
            container.appendChild(errorContainer);
            separator.style.removeProperty('display');

            releaseButton('get-transaction-status','Consultar Estado');
        });
    });

    function getBadgeColorFromStatus(status) {
        const statusColorsDictionary = {
            'Inicializada': 'tbk-badge-warning',
            'Capturada': 'tbk-badge-success',
            'Autorizada': 'tbk-badge-success',
            'Fallida': 'tbk-badge-error',
            'Anulada': 'tbk-badge-info',
            'Reversada': 'tbk-badge-info',
            'Parcialmente anulada': 'tbk-badge-info'
        };

        return statusColorsDictionary[status] ?? 'tbk-badge-default';
    }

    function getFieldName(fieldKey) {
        const fieldNameDictionary = {
            vci: 'VCI',
            status: 'Estado',
            responseCode: 'Código de respuesta',
            amount: 'Monto',
            authorizationCode: 'Código de autorización',
            accountingDate: 'Fecha contable',
            paymentType: 'Tipo de pago',
            installmentType: 'Tipo de cuota',
            installmentNumber: 'Número de cuotas',
            installmentAmount: 'Monto cuota',
            sessionId: 'ID de sesión',
            buyOrder: 'Orden de compra',
            buyOrderMall: 'Orden de compra mall',
            buyOrderStore: 'Orden de compra tienda',
            cardNumber: 'Número de tarjeta',
            transactionDate: 'Fecha transacción',
            transactionTime: 'Hora transacción',
            balance: 'Balance'
        };

        return fieldNameDictionary[fieldKey] ?? fieldKey;
    }

    function createErrorContainer(errorMessage)
    {
        const errorContainer = document.createElement('div');
        errorContainer.classList.add('tbk-status', 'tbk-status-error');
        const icon = document.createElement('i');
        icon.classList.add('fa', 'fa-times');
        const paragraph = document.createElement('p');
        paragraph.textContent = errorMessage;

        errorContainer.appendChild(icon);
        errorContainer.appendChild(paragraph);
        return errorContainer;
    }

    $('#mainform').on('click', '.notice-dismiss', function() {
        let noticeId = $(this).closest('.notice').attr('id');

        post('dismiss_notice', {
            notice_id: noticeId
        });
    });

    function checkPermission(fileToDownload) {
        return $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'check_can_download_file',
                file: fileToDownload
            }
        }).then(function (response) {
            return response;
        }).catch(function (error) {
            return { success: false, data: {
                error: error.message || "Error en la solicitud de descarga"
            } };
        });
    }

    function showNotice(title, message, type = 'success') {
        const notice = $('<div>')
            .addClass(`is-dismissible notice notice-${type}`)
            .prepend(`<p><strong>${title}</strong><br>${message}</p>`);

        const dismissButton = $('<button>')
            .addClass('notice-dismiss');

        notice.append(dismissButton);


        notice.find('.notice-dismiss').on('click', function () {
            notice.fadeOut(300, function () {
                notice.remove();
            });
        });

        $('#logs-container').prepend(notice);
    }

    $('#btnDownload').on('click', function (e) {
        e.preventDefault();
        const logFileSelected = $('#log_file').val();

        if (!logFileSelected) {
            showNotice('Error en la descarga', 'Debes seleccionar un archivo', 'error');
            return;
        }

        checkPermission(logFileSelected).then(function (checkResponse) {
            if (checkResponse.success) {
                window.location.href = checkResponse.data.downloadUrl;
                return;
            }
            showNotice('Error en la descarga', checkResponse.data.error, 'error');

        });
    });
})

const generateRandomString = (length = 6) => {
    const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    const charactersLength = characters.length;
    let result = '';
    const randomValues = new Uint8Array(length);
    crypto.getRandomValues(randomValues);
    for (let i = 0; i < length; i++) {
        result += characters.charAt(randomValues[i] % charactersLength);
    }
    return result;
};

const generateBuyOrderPreview = (format) => {
    const array = new Uint16Array(1);
    crypto.getRandomValues(array);
    const orderId = 10000 + (array[0] % 90000); 
    return format
        .replace(/\{orderId\}/gi, orderId.toString())
        .replace(/\{random(?:, length=\d+)?\}/gi, (_, length) =>
            generateRandomString(length ? parseInt(length, 10) : 6)
        );
};

const isValidFormat = (format) => {
    const allowedCharsRegex = /^[A-Za-z0-9-_:]*$/;
    const formatWithoutPlaceholders = format
        .replace(/\{orderId\}/gi, '') 
        .replace(/\{random(?:, length=\d+)?\}/gi, ''); 
    if (!allowedCharsRegex.test(formatWithoutPlaceholders)) {
        return false;
    }
    return /\{orderId\}/i.test(format); 
};

const createHelpTextBuyOrderFormat = (isOneclick) => {
    const helpText = document.createElement('div');
    helpText.className = 'tbk_buy_order_format_help_text';
    helpText.innerHTML = `
        ${isOneclick ? `<br/><br/>` : ''}
        <p><strong>ℹ️ Información: </strong></p>
        <p><strong>Componentes disponibles:</strong></p>
        <p>•<code>{orderId}</code> Número de orden de compra en Woocommerce (obligatorio).</p>
        <p>•<code>{random}</code> Texto aleatorio con longitud de 8 caracteres (opcional).</p>
        <p>•<code>{random, length=12}</code> Texto aleatorio con longitud especifica (opcional).</p>
        <p><strong>Ejemplo:</strong> <code>cualquierTexto-{random, length=12}-{orderId}</code></p>
        <p><strong>Notas:</strong></p> 
        <p>•Solo se permiten caracteres alfanuméricos, guiones (<code>-</code>), guiones bajos (<code>_</code>)
            o dos puntos (<code>:</code>). No se permiten espacios. </p>  
        <p>•El valor generado no puede exceder los 26 caracteres.</p>
        ${isOneclick ? 
        `<p>•El formato de orden de compra hija debe ser distinto al formato de orden de compra principal.</p>` : ''}
    `;
    return helpText;
}

const attachBuyOrderFormatComponent = (inputId, defaultFormat, isOneclick, otherInputId, addHelpText) => {
    const input = document.getElementById(inputId);
    if (!input) {
        return;
    }

    const wrapper = document.createElement('div');
    wrapper.className = 'tbk_buy_order_format_container';

    input.parentNode.insertBefore(wrapper, input);
    wrapper.appendChild(input);

    const valueDisplay = document.createElement('div');
    valueDisplay.className = 'tbk_buy_order_format_value_display';

    const errorDisplay = document.createElement('div');
    errorDisplay.className = 'tbk_buy_order_format_error_display';

    const btn1 = document.createElement('button');
    btn1.textContent = 'Refrescar';
    btn1.className = 'button button-primary tbk-button-primary';
    btn1.addEventListener('click', (event) => {
        event.preventDefault();
        validateAndDisplay(inputId);
    });

    const btn2 = document.createElement('button');
    btn2.textContent = 'Restablecer';
    btn2.className = 'button button-secondary tbk-button-secondary';
    btn2.addEventListener('click', (event) => {
        event.preventDefault();
        input.value = defaultFormat;
        validateAndDisplay(inputId);
    });

    wrapper.appendChild(btn1);
    wrapper.appendChild(btn2);
    wrapper.parentNode.insertBefore(valueDisplay, wrapper.nextSibling);
    wrapper.parentNode.insertBefore(errorDisplay, valueDisplay.nextSibling);

    if (addHelpText){
        const helpText = createHelpTextBuyOrderFormat(isOneclick);
        wrapper.parentNode.insertBefore(helpText, errorDisplay.nextSibling);
    }

    input._errorDisplay = errorDisplay;
    input._valueDisplay = valueDisplay;
    input._otherInputId = otherInputId;

    const setDisplay = (inputId, message, error) => {
        const input = document.getElementById(inputId);
        if (error){
            input._errorDisplay.style.display = 'block';
            input._errorDisplay.textContent = error;
            input._valueDisplay.textContent = '';
        }
        else{
            input._errorDisplay.style.display = 'none';
            input._valueDisplay.textContent = message;
        }
    };

    const validateAndDisplay = (inputId, isRecursive) => {
        const input = document.getElementById(inputId);
        if (!input?._valueDisplay) {
            return; 
        }
        const value = input.value;
        if (isValidFormat(value)) {
            const preview = generateBuyOrderPreview(value);
            setDisplay(inputId, `✅ Vista previa: ${preview} (${preview.length} caracteres)`, null);
        } else {
            setDisplay(inputId, null, `❌ Formato inválido. Asegúrate de que contenga solo caracteres alfanuméricos, 
                guiones (-), guiones bajos (_) o dos puntos (:), sin espacios, y que contenga {orderId}.`);
            return;
        }
        if (isOneclick){
            const otherInput = document.getElementById(input._otherInputId);
            if (!otherInput) {
                return; 
            }
            const otherFormat = otherInput.value;
            if (otherFormat && value && (otherFormat.toUpperCase() === value.toUpperCase())) {
                setDisplay(inputId, null, `❌ El formato de orden de compra hija no puede ser igual 
                    al formato de orden de compra principal.`);
            }
            if (!isRecursive){
                validateAndDisplay(input._otherInputId, true);
            }
        }
    };

    input.addEventListener('input', (event) => {
        validateAndDisplay(inputId);
    });

    validateAndDisplay(inputId);
}

document.addEventListener("DOMContentLoaded", function() {
    attachBuyOrderFormatComponent('woocommerce_transbank_webpay_plus_rest_buy_order_format', 
        'wc-{random, length=8}-{orderId}', false, null, true);
    attachBuyOrderFormatComponent('woocommerce_transbank_oneclick_mall_rest_buy_order_format', 
        'wc-{random, length=8}-{orderId}', true, 'woocommerce_transbank_oneclick_mall_rest_child_buy_order_format', false);
    attachBuyOrderFormatComponent('woocommerce_transbank_oneclick_mall_rest_child_buy_order_format', 
        'wc-child-{random, length=8}-{orderId}', true, 'woocommerce_transbank_oneclick_mall_rest_buy_order_format', true);
});
