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
        $(`.${btnName}`).data('sending', true).html(msg);
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
        if (!blockButton(e, 'get-transaction-status', 'Consultando al API REST...')){
            return;
        }
        post('get_transaction_status', {
            order_id: $('.get-transaction-status').data('order-id'),
            buy_order: $('.get-transaction-status').data('buy-order'),
            token: $('.get-transaction-status').data('token')
        }, (resp) => {
            let $table = $('.transaction-status-response');
            let statusData = resp.status;
            if(resp.product == "webpay_plus"){
                $("#tbk_wpp_vci").removeClass("tbk-hide");
                $("#tbk_wpp_session_id").removeClass("tbk-hide");
            }else{
                $("#tbk_wpoc_commerce_code").removeClass("tbk-hide");
            }
            const statusDataKeys = Object.keys(statusData);
            statusDataKeys.forEach(key => {
                let value = statusData[key] ? statusData[key] : '-';
                const tableRow = $table.find('.status-' + key);
                tableRow.html(value);
            });
            $table.find('.status-product').html(resp.product);
            let niceJson = JSON.stringify(resp.raw, null, 2)
            $table.find('.status-raw').html(`<pre>${niceJson}</pre>`);
            $table.show();
            releaseButton('get-transaction-status','Consultar estado de la transacción');
        }, (error) => {
            $('.error-status-raw').html(`<p>${error.responseJSON.message}</p>`);
            $('.error-transaction-status-response').show();
            releaseButton('get-transaction-status','Consultar estado de la transacción');
        });
    });

    $('#mainform').on('click', '.notice-dismiss', function() {
        let noticeId = $(this).closest('.notice').attr('id');

        post('dismiss_notice', {
            notice_id: noticeId
        });
    });
})
