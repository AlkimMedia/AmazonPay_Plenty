<?php
namespace AmazonLoginAndPay\Providers;

use Plenty\Plugin\Templates\Twig;

class AmzDataProviderJavascript
{
    public function call(Twig $twig)
    {
        return $twig->render('AmazonLoginAndPay::snippets.javascript', []);
    }
}