<?php

namespace AmazonLoginAndPay\Providers;

use AmazonLoginAndPay\Helpers\AlkimAmazonLoginAndPayHelper;
use Plenty\Plugin\Templates\Twig;

class AmzDataProviderCartButton
{
    public function call(Twig $twig)
    {
        /** @var AlkimAmazonLoginAndPayHelper $helper */
        $helper = pluginApp(AlkimAmazonLoginAndPayHelper::class);
        $error  = $helper->getFromSession('amazonCheckoutError');
        $helper->setToSession('amazonCheckoutError', '');

        return $twig->render('AmazonLoginAndPay::snippets.cart-button', ['error' => $error]);
    }
}