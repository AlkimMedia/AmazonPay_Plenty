<?php

namespace AmazonLoginAndPay\Providers;

use Plenty\Plugin\RouteServiceProvider;
use Plenty\Plugin\Routing\Router;

class AmzRouteServiceProvider extends RouteServiceProvider
{
    public function map(Router $router)
    {
        $router->get('amazon-checkout', 'AmazonLoginAndPay\Controllers\AmzContentController@amazonCheckoutAction');
        $router->get('amazon-checkout-wallet', 'AmazonLoginAndPay\Controllers\AmzContentController@amazonCheckoutWalletAction');
        $router->get('amazon-login-processing', 'AmazonLoginAndPay\Controllers\AmzContentController@amazonLoginProcessingAction');
        $router->match(['post', 'get'], 'amazon-connect-accounts', 'AmazonLoginAndPay\Controllers\AmzContentController@amazonConnectAccountsAction');
        $router->get('amazon-checkout-proceed', 'AmazonLoginAndPay\Controllers\AmzContentController@amazonCheckoutProceedAction');
        $router->get('amazon-ajax-handle', 'AmazonLoginAndPay\Controllers\AjaxController@handle');
        $router->get('amazon-pre-checkout', 'AmazonLoginAndPay\Controllers\AjaxController@preCheckoutActions');
        $router->get('amazon-cron', 'AmazonLoginAndPay\Controllers\AjaxController@cron');
        $router->post('amazon-ipn', 'AmazonLoginAndPay\Controllers\AjaxController@ipn');
        $router->post('amazon-shopware-connect', 'AmazonLoginAndPay\Controllers\AjaxController@shopwareConnect');

        //This is for debugging only:
        //$router->get('amazon-get-table', 'AmazonLoginAndPay\Controllers\AjaxController@getTable');
    }
}
