jQuery(function($) {
    $(".check_conn").on("click",function(e) {

        $(".check_conn").text("Verificando ...");

        $("#tbk_response_status").removeClass("tbk-hide");
        $("#div_status_ok").addClass("tbk-hide");
        $("#div_status_error").addClass("tbk-hide");

        $.post(ajax_object.ajax_url, {action: 'check_connection', nonce: ajax_object.nonce}, function(response){
            $(".check_conn").text("Verificar Conexión");

            if(response.status.string == "OK") {
                $("#response_url_text").text(response.response.url);
                $("#response_token_text").html('<pre>'+response.response.token+'</pre>');

                $("#div_status_ok").removeClass("tbk-hide");

            } else {
                $("#error_response_text").text(response.response.error);
                $("#error_detail_response_text").html('<code style="display: block; padding: 10px">'+response.response.detail+'</code>');

                $("#div_status_error").removeClass("tbk-hide");
            }

        })

        e.preventDefault();
    });

    $('.get-transaction-status').on("click",function(e) {
        if ($(this).data('sending') === true) {
            return;
        }
        $(this).data('sending', true);
        $(this).html('Consultando al API REST...');
        e.preventDefault();

        $.post(ajax_object.ajax_url, {
            action: 'get_transaction_status',
            order_id: $(this).data('order-id'),
            buy_order: $(this).data('buy-order'),
            token: $(this).data('token'),
            nonce: window.ajax_object.nonce
        }, function(response){
            let $table = $('.transaction-status-response');
            let statusData = response.status;
            if(response.product == "webpay_plus"){
                $("#tbk_wpp_vci").removeClass("tbk-hide");
                $("#tbk_wpp_session_id").removeClass("tbk-hide");
            }else{
                $("#tbk_wpoc_commerce_code").removeClass("tbk-hide");
            }
            Object.keys(statusData).forEach(key => {
                let value = statusData[key] ? statusData[key] : '-';
                $table.find('.status-' + key).html(value);
            });
            $table.find('.status-product').html(response.product);

            let niceJson = JSON.stringify(response.raw, null, 2)
            $table.find('.status-raw').html(`<pre>${niceJson}</pre>`);
            $table.show();
            $(this).data('sending', false);
            $(this).html('Consultar estado de la transacción');

        }.bind(this))
            .fail(function(e, a) {
                $('.error-status-raw').html(`<p>${e.responseJSON.message}</p>`);
                $('.error-transaction-status-response').show();
                $(this).data('sending', false);
                $(this).html('Consultar estado de la transacción');
            })
    });


    $(".check_exist_tables").on("click",function(e) {
        $(".check_exist_tables").text("Verificando ...");
        $("#tbk-tbl-response-title").removeClass("tbk-hide");
        $("#div_tables_status").addClass("tbk-hide");
        $("#div_tables_status_result").addClass("tbk-hide");
        $("#div_tables_error").addClass("tbk-hide");
        $("#tbl_response_status_text").removeClass();

        $.post(ajax_object.ajax_url, {action: 'check_exist_tables', nonce: ajax_object.nonce}, function(response){
            $(".check_exist_tables").text("Verificar Tablas");
            $("#div_tables_status").removeClass("tbk-hide");

            if(!response.error) {

                $("#tbl_response_status_text").addClass("label-success").text("OK").show();
                $("#tbl_response_result_text").text(response.msg);

                $("#div_tables_status_result").removeClass("tbk-hide");

            } else {

                $("#tbl_response_status_text").addClass("label-danger").text("ERROR").show();
                $("#tbl_error_message_text").html('<code style="display: block; padding: 10px">' + response.error + ' Exception: ' + response.exception +'</code>');

                $("#div_tables_error").removeClass("tbk-hide");
            }

        })

        e.preventDefault();
    });

})
