<?php

namespace AmazonLoginAndPay\Providers;

use Plenty\Plugin\Templates\Twig;

class AmzDataProviderLoginButton
{
    public function call(Twig $twig)
    {

        return $twig->render('AmazonLoginAndPay::snippets.login-button', []);
    }
}