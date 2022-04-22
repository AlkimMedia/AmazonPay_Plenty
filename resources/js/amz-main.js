var amz$;
var PlentyMarketsAmazonPay = {
    isFirstAddressCall: true,
    amazonButtonCounter: 0,
    amazonLoginButtonCounter: 0,
    isInitialized: false,
    isInitStarted: false,
    isAddressInitialized: false,
    isCompletelyInitialized: false,
    isDocumentReady: false,
    getShippingListTimeout: null,
    amazonScope: 'profile postal_code payments:widget payments:shipping_address payments:billing_address',
    orderReference: null,
    getLanguage: function () {
        var language = 'en-GB';
        if (typeof App !== 'undefined') {
            if (typeof App.language !== 'undefined') {
                switch (App.language) {
                    case 'de':
                        language = 'de-DE';
                        break;
                    case 'es':
                        language = 'es-ES';
                        break;
                    case 'fr':
                        language = 'fr-FR';
                        break;
                    case 'it':
                        language = 'it-IT';
                        break;
                }
            }
        }
        return language;
    },
    getShippingList: function (callback) {
        amz$.get(amazonLoginAndPay.urls.amazonAjaxHandle, {
            action: 'getShippingList',
            orderReference: String(PlentyMarketsAmazonPay.orderReference)
        }, function (data) {
            if (data.indexOf('alert-warning') !== -1) {
                amz$('.amz-checkout-order-button-wr').hide();
            } else {
                amz$('.amz-checkout-order-button-wr').show();
            }
            amz$('#shippingOptionsListWr').html(data);
            if (typeof callback !== 'undefined') {
                callback();
            }
        });
    },
    getOrderDetails: function () {
        amz$.get(amazonLoginAndPay.urls.amazonAjaxHandle, {
            action: 'getOrderDetails',
            orderReference: String(PlentyMarketsAmazonPay.orderReference)
        }, function (data) {
            amz$('#orderDetailsWr').html(data);
        });
    },
    logout: function () {
        if (typeof (amazon) !== 'undefined') {
            amazon.Login.logout();
            if (PlentyMarketsAmazonPay.logoutInterval) {
                clearInterval(PlentyMarketsAmazonPay.logoutInterval);
            }
        }
        document.cookie = "amazon_Login_accessToken=; expires=Thu, 01 Jan 1970 00:00:00 GMT";
        document.cookie = "amzDummy=" + (new Date().getTime()) + ";secure;path=/";
    },
    getURLParameter: function (name, source) {
        return decodeURIComponent((new RegExp('[?|&|#]' + name + '=' +
            '([^&]+?)(&|#|;|$)').exec(source) || [, ""])[1].replace(/\+/g,
            '%20')) || null;
    },

    getShippingListService: function (useTimeout) {
        if (PlentyMarketsAmazonPay.getShippingListTimeout) {
            clearTimeout(PlentyMarketsAmazonPay.getShippingListTimeout);
        }
        if (useTimeout) {
            PlentyMarketsAmazonPay.getShippingListTimeout = setTimeout(function () {
                PlentyMarketsAmazonPay.getShippingList(PlentyMarketsAmazonPay.getOrderDetails);
            }, 1000);
        } else {
            PlentyMarketsAmazonPay.getShippingList(PlentyMarketsAmazonPay.getOrderDetails);
        }
    },


    initialize: function () {
        if (typeof jQuery !== 'undefined' && jQuery.fn.on && typeof amz$ === 'undefined') {
            amz$ = jQuery;
        }
        if (typeof amz$ !== 'undefined' && PlentyMarketsAmazonPay.isInitStarted === false && PlentyMarketsAmazonPay.isDocumentReady) {
            PlentyMarketsAmazonPay.isInitStarted = true;
            var authRequest;

            var $payButton = amz$('.amzPayButton');
            if ($payButton.length) {
                $payButton.each(function () {
                    var $button = amz$(this);
                    if ($button.find('img').length === 0 || !$button.attr('id')) {
                        var isArticleCheckout = $button.hasClass('articleCheckout');
                        var id = 'amzPayButton_' + PlentyMarketsAmazonPay.amazonButtonCounter++;
                        $button.attr('id', id);
                        OffAmazonPayments.Button(id, amazonLoginAndPay.config.merchantId, {
                            type: 'PwA',
                            color: amazonLoginAndPay.config.payButtonColor,
                            size: amazonLoginAndPay.config.payButtonSize,
                            language: PlentyMarketsAmazonPay.getLanguage(),

                            authorization: function () {
                                var doAmzAuth = function () {
                                    var loginOptions = {
                                        scope: PlentyMarketsAmazonPay.amazonScope,
                                        popup: amazonLoginAndPay.config.popup
                                    };
                                    document.cookie = "amzLoginType=Pay;path=/";
                                    document.cookie = "amzDummy=" + (new Date().getTime()) + ";secure;path=/";
                                    if (amazonLoginAndPay.config.popup && isArticleCheckout) {
                                        authRequest = amazon.Login.authorize(loginOptions);
                                    } else {
                                        authRequest = amazon.Login.authorize(loginOptions, amazonLoginAndPay.urls.amazonLoginProcessing);
                                    }
                                };
                                if (isArticleCheckout && !amazonLoginAndPay.config.popup) {
                                    PlentyMarketsAmazonPay.buyProduct(doAmzAuth);
                                } else {
                                    doAmzAuth();
                                }
                            },

                            onSignIn: function (orderReference) {
                                PlentyMarketsAmazonPay.orderReference = orderReference;
                                if (amazonLoginAndPay.config.popup && isArticleCheckout) {
                                    PlentyMarketsAmazonPay.buyProduct(function () {
                                        location.href = amazonLoginAndPay.urls.amazonLoginProcessing + '?access_token=' + authRequest.access_token;
                                    });
                                }
                            },
                            onError: function (error) {
                                console.error(error.getErrorMessage(), error.getErrorCode());
                            }
                        });
                        $button.find('img').show();
                        if (isArticleCheckout) {
                            var buttonChecker = function () {
                                if (amz$('.add-to-basket-container').length) {
                                    $button.show();
                                } else {
                                    $button.hide();
                                }
                            };
                            buttonChecker();
                            setInterval(buttonChecker, 200);
                        }
                    }
                });
            }


            var $loginButton = amz$('.amzLoginButton');
            if ($loginButton.length) {
                $loginButton.each(function () {
                    var $button = amz$(this);
                    if ($button.find('img').length === 0 || !$button.attr('id')) {
                        var id = 'amzLoginButton_' + PlentyMarketsAmazonPay.amazonLoginButtonCounter++;
                        $button.attr('id', id);
                        OffAmazonPayments.Button(id, amazonLoginAndPay.config.merchantId, {
                            type: 'LwA',
                            color: amazonLoginAndPay.config.loginButtonColor,
                            size: amazonLoginAndPay.config.loginButtonSize,
                            language: PlentyMarketsAmazonPay.getLanguage(),
                            authorization: function () {
                                var loginOptions = {
                                    scope: PlentyMarketsAmazonPay.amazonScope,
                                    popup: amazonLoginAndPay.config.popup
                                };
                                if (location.href.indexOf('%2Fcheckout') !== -1 || location.href.indexOf('/checkout') !== -1) {
                                    document.cookie = "amzLoginType=Pay;path=/";
                                    document.cookie = "amzDummy=" + (new Date().getTime()) + ";secure;path=/";
                                } else {
                                    document.cookie = "amzLoginType=Login;path=/";
                                    document.cookie = "amzDummy=" + (new Date().getTime()) + ";secure;path=/";
                                }
                                authRequest = amazon.Login.authorize(loginOptions, amazonLoginAndPay.urls.amazonLoginProcessing);
                            },
                            onError: function (error) {
                                console.error(error.getErrorMessage(), error.getErrorCode());
                            }
                        });
                        $button.find('img').show();
                    }
                });
            }


            if (amz$('#addressBookWidgetDiv').length) {
                new OffAmazonPayments.Widgets.AddressBook({
                    sellerId: amazonLoginAndPay.config.merchantId,
                    scope: PlentyMarketsAmazonPay.amazonScope,
                    onOrderReferenceCreate: function (orderReference) {
                        orderReference = orderReference.getAmazonOrderReferenceId();
                        PlentyMarketsAmazonPay.orderReference = orderReference;
                        if (PlentyMarketsAmazonPay.isInitialized === false) {
                            amz$.get(amazonLoginAndPay.urls.amazonAjaxHandle, {
                                action: 'setOrderReference',
                                orderReference: orderReference
                            }, function () {
                                PlentyMarketsAmazonPay.isInitialized = true;
                                if (PlentyMarketsAmazonPay.isAddressInitialized) {
                                    PlentyMarketsAmazonPay.getShippingListService(true);
                                    setTimeout(PlentyMarketsAmazonPay.getShippingList, 2000);
                                }
                            });
                        }
                    },
                    onAddressSelect: function () {
                        PlentyMarketsAmazonPay.isAddressInitialized = true;
                        if (PlentyMarketsAmazonPay.isInitialized) {
                            PlentyMarketsAmazonPay.getShippingListService(true);
                        }
                    },
                    design: {
                        designMode: 'responsive'
                    },
                    onReady: function (orderReference) {
                        // Enter code here you want to be executed
                        // when the address widget has been rendered.
                    },

                    onError: function (error) {
                        console.error(error.getErrorMessage(), error.getErrorCode());
                    }
                }).bind("addressBookWidgetDiv");
            } else if (amz$('#walletWidgetDiv').length) {
                PlentyMarketsAmazonPay.getOrderDetails();
            }
            if (amz$('#walletWidgetDiv').length) {
                var amzCurrency = 'EUR';
                if (amz$('#currency-input').length) {
                    amzCurrency = amz$('#currency-input').val();
                }
                amazonLoginAndPay.widgets.walletWidget = new OffAmazonPayments.Widgets.Wallet({
                    sellerId: amazonLoginAndPay.config.merchantId,
                    scope: PlentyMarketsAmazonPay.amazonScope,
                    presentmentCurrency: amzCurrency,
                    design: {
                        designMode: 'responsive'
                    },
                    onPaymentSelect: function () {
                        PlentyMarketsAmazonPay.getShippingListService(false);
                    },
                    onError: function (error) {
                        // your error handling code
                        console.error(error.getErrorMessage(), error.getErrorCode());
                    }
                }).bind("walletWidgetDiv");
            }
            setTimeout(function () {
                PlentyMarketsAmazonPay.isInitStarted = false;
            }, 1000);
        } else {
            setTimeout(PlentyMarketsAmazonPay.initialize, 100);
        }
    },
    cron: function () {

        setInterval(function () {
            if (typeof amz$ !== 'undefined') {
                var $unrenderedButtons = amz$('.amzPayButton:not([id]), .amzLoginButton:not([id])');
                if ($unrenderedButtons.length) {
                    PlentyMarketsAmazonPay.initialize();
                }
            }
        }, 1000);

    },
    getCookieValue: function (name) {
        var b = document.cookie.match('(^|;)\\s*' + name + '\\s*=\\s*([^;]+)');
        return b ? b.pop() : '';
    },
    buyProduct: function (callback) {

        var id = null;
        if (typeof window.ceresStore.state.item !== 'undefined' && window.ceresStore.state.item.variation.documents[0]) {
            id = window.ceresStore.state.item.variation.documents[0].data.variation.id;
        } else if (typeof window.ceresStore.state.items.mainItemId !== 'undefined' && window.ceresStore.state.items[window.ceresStore.state.items.mainItemId]) {
            id = window.ceresStore.state.items[window.ceresStore.state.items.mainItemId].variation.documents[0].data.variation.id;
        }
        if (id) {
            var postData = {
                variationId: id,
                quantity: amz$('.add-to-basket-container').find('input[type="text"], input[type="number"]').first().val()
            };
            amz$.post(
                '/rest/io/basket/items/',
                postData,
                function () {
                    callback();
                }
            );
        } else {
            callback();
        }
    }

};


var amazonPayOnLoad = function () {
    if (typeof OffAmazonPayments.jQuery !== 'undefined' && typeof amz$ === 'undefined') {
        amz$ = OffAmazonPayments.jQuery;
    }
    var accessToken;
    if (location.href.indexOf('amazon-login-processing/?access_token=') !== -1 || location.href.indexOf('amazon-login-processing?access_token=') !== -1) {
        accessToken = PlentyMarketsAmazonPay.getURLParameter("access_token", location.href);
    } else {
        accessToken = PlentyMarketsAmazonPay.getURLParameter("access_token", location.hash);
    }

    if (typeof accessToken === 'string' && accessToken.match(/^Atza/)) {
        amz$.get(amazonLoginAndPay.urls.amazonAjaxHandle, {
            action: 'setAccessToken',
            access_token: accessToken,
            do_login: (PlentyMarketsAmazonPay.getCookieValue('amzLoginType') === 'Login' ? 1 : 0)
        }, function (data) {
            document.cookie = "amzLoginType=Pay;path=/";
            document.cookie = "amzDummy=" + (new Date().getTime()) + ";secure;path=/";
            var obj = JSON.parse(data.trim());
            if (obj.redirect) {
                location.href = obj.redirect;
            }
        });
        document.cookie = "amazon_Login_accessToken=" + accessToken + ";secure;path=/";
        document.cookie = "amzDummy=" + (new Date().getTime()) + ";secure;path=/";
    }
    amz$(document).on('change', '#shippingOptionsListWr [name="ShippingProfileID"]', function () {
        if (amz$(this).is(':checked')) {
            var id = amz$(this).val();
            amz$.get(amazonLoginAndPay.urls.amazonAjaxHandle, {
                action: 'setShippingProfileId',
                id: id
            }, function (data) {
                amz$('#orderDetailsWr').html(data);
            });
        }
    });


    amz$(function () {
        PlentyMarketsAmazonPay.isDocumentReady = true;
        amz$('.amz-checkout-order-button-wr:not(.wallet-only) a').bind('click', function (e) {
            e.preventDefault();
            if (amz$('#gtc-accept').length && !amz$('#gtc-accept').is(':checked')) {
                e.preventDefault();
                alert(amz$('#gtc-accept').data('error'));
                return;
            }
            var $link = amz$(this);
            $link.css({opacity: 0.5, cursor: 'default'});
            $link.unbind('click').bind('click', function (e) {
                e.preventDefault();
            });

            var confirmOrderReference = function (confirmationFlow) {
                amz$.get(amazonLoginAndPay.urls.amazonPreCheckout, function (data) {
                    if (typeof data === 'object' && data.redirect) {
                        confirmationFlow.error();
                        location.href = data.redirect;
                    } else {
                        confirmationFlow.success();
                    }
                });
            };

            var startCheckout = function () {
                OffAmazonPayments.initConfirmationFlow(amazonLoginAndPay.config.merchantId, PlentyMarketsAmazonPay.orderReference, function (confirmationFlow) {
                    confirmOrderReference(confirmationFlow);
                });
            };

            var $commentInput = amz$('.amz-comment-textarea');
            if ($commentInput.length) {
                amz$.get(amazonLoginAndPay.urls.amazonAjaxHandle, {
                    action: 'setComment',
                    comment: $commentInput.val()
                }, function () {
                    startCheckout();
                });
            } else {
                startCheckout();
            }
        });

        amz$('.amz-checkout-order-button-wr.wallet-only a').bind('click', function (e) {
            e.preventDefault();
            var $link = amz$(this);
            $link.css({opacity: 0.5, cursor: 'default'});
            $link.unbind('click').bind('click', function (e) {
                e.preventDefault();
            });
            amz$.get(amazonLoginAndPay.urls.amazonPreCheckout, function (data) {
                if (typeof data === 'object' && data.redirect) {
                    location.href = data.redirect;
                } else {
                    location.href = $link.attr('href');
                }
            });
        });
    });
};

var amazonLoadInterval = setInterval(function(){
    if(typeof OffAmazonPayments !== 'undefined' && OffAmazonPayments.jQuery){
        clearInterval(amazonLoadInterval);
        amazonPayOnLoad();
    }
}, 200);




window._onAmazonLoginReady = function () {
    amazonLoginAndPay.isAmazonLoginReady;
    if (typeof OffAmazonPayments.jQuery !== 'undefined' && typeof amz$ === 'undefined') {
        amz$ = OffAmazonPayments.jQuery;
    }
    amazon.Login.setClientId(amazonLoginAndPay.config.clientId);
    var $actionInputField = amz$('[name="amazon-pay-action"]');
    if ($actionInputField.length) {
        var amazonPayAction = $actionInputField.val();
    }
    if (amazonPayAction) {
        if (amazonPayAction === 'logout') {
            PlentyMarketsAmazonPay.logout();
            PlentyMarketsAmazonPay.logoutInterval = setInterval(PlentyMarketsAmazonPay.logout, 500);
        }
    }

    amazon.Login.setUseCookie(true);
    amazon.Login.setRegion("EU");
    if (amazonLoginAndPay.config.sandbox) {
        amazon.Login.setSandboxMode(!!(amazonLoginAndPay.config.sandbox && amazonLoginAndPay.config.sandbox === 'true'));
    }
};

window._onAmazonPaymentsReady = function () {
    PlentyMarketsAmazonPay.initialize();
    PlentyMarketsAmazonPay.cron();
};

if (amazonLoginAndPay.isAmazonLoginReady) {
    window._onAmazonLoginReady();
}else {
    window.onAmazonLoginReady = window._onAmazonLoginReady;
}

if (amazonLoginAndPay.isAmazonPaymentsReady) {
    window._onAmazonPaymentsReady();
}else {
    window.onAmazonPaymentsReady = window._onAmazonPaymentsReady;
}

