<?php

namespace AmazonLoginAndPay\Providers;

use Plenty\Plugin\Templates\Twig;

class AmzDataProviderArticleCheckoutButton
{
    public function call(Twig $twig)
    {
        return $twig->render('AmazonLoginAndPay::snippets.article-checkout-button', []);
    }
}