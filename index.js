function submitForm(){
    $('.paypal-btn').hide();
    $('.spinner').show();
    $('.invalid-feedback').remove();
    $('.is-invalid').removeClass('is-invalid');
    var formData = {
        amount: $('#amount').val(),
        currency: $('#currency').val()
    };
    $.ajax({
        type: 'POST',
        url: 'payment.php',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.status === 'error') {
                $('.paypal-btn').show();
                $('.spinner').hide();
                $.each(response.errors, function(field, message) {
                    $('#' + field).addClass('is-invalid');
                    $('#' + field).after('<div class="invalid-feedback d-block">' + message + '</div>');
                });
            } else {
                $('.paypal-btn').hide();
                $('.spinner').show();
                if(response.status === "success" ){
                    window.location.href = response.url;
                } else {
                    console.log(response);
                    window.location.href = "error.html?message="+response.message ;
                }
            }
        },
        error: function(error) {
            console.log('Error:', error);
        }
    });
}
