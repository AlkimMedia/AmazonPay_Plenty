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
        $helper->log(__CLASS__, __METHOD__, 'logout status pre', [$helper->getFromSession('amazonLogout')]);
        if ($helper->getFromSession('amazonLogout') === true || $helper->getFromSession('amazonLogout') === 1 || $helper->getFromSession('amazonLogout') === null) {
            $logout = true;
            $helper->setToSession('amazonLogout', 2);
        }
        $helper->log(__CLASS__, __METHOD__, 'logout status', [$logout, $helper->getFromSession('amazonLogout')]);
        return $twig->render('AmazonLoginAndPay::snippets.javascript', ['logout' => $logout]);
    }
}