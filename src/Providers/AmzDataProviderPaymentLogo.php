<?php

namespace AmazonLoginAndPay\Providers;

use Plenty\Plugin\Templates\Twig;

class AmzDataProviderPaymentLogo
{
    public function call(Twig $twig)
    {
        return $twig->render('AmazonLoginAndPay::snippets.payment-logo', []);
    }
}