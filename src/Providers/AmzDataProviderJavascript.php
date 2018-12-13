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
        return $twig->render('AmazonLoginAndPay::snippets.javascript', ['logout' => $logout]);
    }
}