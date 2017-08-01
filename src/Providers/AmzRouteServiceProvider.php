<?php

namespace AmazonLoginAndPay\Providers;

use AmazonLoginAndPay\Helpers\AlkimAmazonLoginAndPayHelper;
use Plenty\Plugin\RouteServiceProvider;
use Plenty\Plugin\Routing\Router;

class AmzRouteServiceProvider extends RouteServiceProvider
{
    public function map(Router $router)
    {
        $helper = pluginApp(AlkimAmazonLoginAndPayHelper::class);
        $helper->log(__CLASS__, __METHOD__, 'map', []);
        $router->get('amazon-checkout','AmazonLoginAndPay\Controllers\AmzContentController@amazonCheckoutAction');
        $router->get('amazon-checkout-wallet', 'AmazonLoginAndPay\Controllers\AmzContentController@amazonCheckoutWalletAction');
        $router->get('amazon-login-processing', 'AmazonLoginAndPay\Controllers\AmzContentController@amazonLoginProcessingAction');
        $router->get('amazon-checkout-proceed', 'AmazonLoginAndPay\Controllers\AmzContentController@amazonCheckoutProceedAction');
        $router->get('amazon-ajax-handle', 'AmazonLoginAndPay\Controllers\AjaxController@handle');
        //$router->get('data-test', 'AmazonLoginAndPay\Controllers\AjaxController@dataTest');
        $router->get('amazon-cron', 'AmazonLoginAndPay\Controllers\AjaxController@cron');
        $router->post('amazon-ipn', 'AmazonLoginAndPay\Controllers\AjaxController@ipn');
        $helper->log(__CLASS__, __METHOD__, 'mapped', []);
    }
}
