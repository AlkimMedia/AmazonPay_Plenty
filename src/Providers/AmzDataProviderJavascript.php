<?php

namespace AmazonLoginAndPay\Providers;

use AmazonLoginAndPay\Helpers\AlkimAmazonLoginAndPayHelper;
use Plenty\Plugin\Templates\Twig;

class AmzDataProviderJavascript
{
    public function call(Twig $twig)
    {
        /** @var AlkimAmazonLoginAndPayHelper $helper */
        $helper = pluginApp(AlkimAmazonLoginAndPayHelper::class);
        $logout = false;
        if ($helper->getFromSession('amazonLogout') === true || $helper->getFromSession('amazonLogout') === 1 || $helper->getFromSession('amazonLogout') === null) {
            $logout = true;
            $helper->setToSession('amazonLogout', 2);
        }
        $urls = [
            'amazon_login_processing' => $helper->getAbsoluteUrl('amazon-login-processing'),
            'amazon_ajax_handle'      => $helper->getAbsoluteUrl('amazon-ajax-handle'),
            'amazon_pre_checkout'     => $helper->getAbsoluteUrl('amazon-pre-checkout')
        ];

        return $twig->render('AmazonLoginAndPay::snippets.javascript', ['logout' => $logout, 'urls' => $urls]);
    }
}