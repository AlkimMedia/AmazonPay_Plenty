console.log('yeah');

window.onAmazonLoginReady = function () {
    amazon.Login.setClientId(amazonLoginAndPay.config.clientId);
};

window.onAmazonPaymentsReady = function () {
    // render the button here
    var authRequest;

    OffAmazonPayments.Button('amzCartButton', amazonLoginAndPay.config.merchantId, {
        type: 'PwA',
        color: amazonLoginAndPay.config.payButtonColor,
        size: amazonLoginAndPay.config.payButtonSize,
        language: 'de-DE',

        authorization: function () {
            loginOptions = {
                scope: 'profile postal_code payments:widget payments:shipping_address payments:billing_address',
                popup: amazonLoginAndPay.config.popup
            };
            authRequest = amazon.Login.authorize(loginOptions, '/amazon-login-processing/');
        },
        /*
        onSignIn: function (orderReference) {
            if (thisId == 'amazonChangePaymentLogin') {
                location.reload();
            } else {
                var amazonOrderReferenceId = orderReference.getAmazonOrderReferenceId();

                 $.ajax({
                 type: 'GET',
                 url: '<?php echo xtc_href_link('checkout_amazon_handler.php', '', $request_type); ?>',
                 data: 'handleraction=setusertoshop&access_token=' + authRequest.access_token + '&amazon_id=' + amazonOrderReferenceId,
                 success: function(htmlcontent){
                 if (htmlcontent == 'error') {
                 alert('An error occured - please try again or contact our support');
                 } else {
                 window.location = htmlcontent;
                 }
                 }

         });
            }
         },*/
        onError: function (error) {
            console.error(error);
        }
    });
}

