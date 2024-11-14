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
                fieldValue.textContent = value.toString();

                if(key == 'status') {
                    fieldValue.classList.add('tbk-badge');
                    fieldValue.classList.add(getBadgeColorFromStatus(value.toString()));
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
