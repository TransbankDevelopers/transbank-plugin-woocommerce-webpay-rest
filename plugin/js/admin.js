function updateConfig(){
}
jQuery(function($) {
  $(".check_conn").on("click",function(e) {

    $(".check_conn").text("Verificando ...");
    $("#response_title").hide();
    $("#row_response_status").hide();
    $("#row_response_url").hide();
    $("#row_response_token").hide();
    $("#row_error_message").hide();
    $("#row_error_detail").hide();
    $(".tbk_table_trans").empty();

    $.post(ajax_object.ajax_url, {action: 'check_connection', nonce: ajax_object.nonce}, function(response){
      $(".check_conn").text("Verificar Conexión");
      $('.tbk-response-title').show();
      $("#response_title").show();
      $("#row_response_status").show();
      $("#row_response_status_text").removeClass("label-success").removeClass("label-danger");

      if(response.status.string == "OK") {

        $("#row_response_status_text").addClass("label-success").text("OK").show();
        $("#row_response_url_text").append(response.response.url);
        $("#row_response_token_text").append('<pre>'+response.response.token_ws+'</pre>');

        $("#row_response_url").show();
        $("#row_response_token").show();

      } else {

        $("#row_response_status_text").addClass("label-danger").text("ERROR").show();
        $("#row_error_message_text").append(response.response.error);
        $("#row_error_detail_text").append('<code style="display: block; padding: 10px">'+response.response.detail+'</code>');

        $("#row_error_message").show();
        $("#row_error_detail").show();
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
          console.log(response);

          let $table = $('.transaction-status-response');
          let statusData = response.status;
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
          alert('Falló la consulta de cuotas: ' + e.responseJSON.message)
          $(this).data('sending', false);
          $(this).html('Consultar estado de la transacción');
      })
  });


  $(".check_exist_tables").on("click",function(e) {

    $(".check_exist_tables").text("Verificando ...");
    $("#row_tbl_response_status").hide();
    $("#row_tbl_response_result").hide();
    $("#row_tbl_error_message").hide();
    $('.tbk-tbl-response-title').show();
    $(".tbk_tbl_table_trans").empty();

    $.post(ajax_object.ajax_url, {action: 'check_exist_tables', nonce: ajax_object.nonce}, function(response){
      $(".check_exist_tables").text("Verificar Tablas");
      $("#row_tbl_response_status").show();
      $("#row_tbl_response_status_text").removeClass("label-success").removeClass("label-danger");

      if(!response.error) {

        $("#row_tbl_response_status_text").addClass("label-success").text("OK").show();
        $("#row_tbl_response_result_text").append(response.msg);

        $("#row_tbl_response_result").show();

      } else {

        $("#row_tbl_response_status_text").addClass("label-danger").text("ERROR").show();
        $("#row_tbl_error_message_text").append('<code style="display: block; padding: 10px">' + response.error + ' Exception: ' + response.exception +'</code>');

        $("#row_tbl_error_message").show();
      }

    })

    e.preventDefault();
  });

})



