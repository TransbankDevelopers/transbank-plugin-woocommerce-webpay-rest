function updateConfig(){
}
jQuery(function($) {
  $(".check_conn").click(function(e) {

    $(".check_conn").text("Verificando ...");
    $("#response_title").hide();
    $("#row_response_status").hide();
    $("#row_response_url").hide();
    $("#row_response_token").hide();
    $("#row_error_message").hide();
    $("#row_error_detail").hide();
    $(".tbk_table_trans").empty();

    $.post(ajax_object.ajax_url, {action: 'check_connection'}, function(response){
        console.log('RESPONSE', response)
      $(".check_conn").text("Verificar Conexión");
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

})
