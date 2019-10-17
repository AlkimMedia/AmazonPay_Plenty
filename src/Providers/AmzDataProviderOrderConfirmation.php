<?php

namespace AmazonLoginAndPay\Providers;

use AmazonLoginAndPay\Helpers\AlkimAmazonLoginAndPayHelper;
use Plenty\Plugin\Templates\Twig;

class AmzDataProviderOrderConfirmation
{
    public function call(Twig $twig)
    {
        /** @var AlkimAmazonLoginAndPayHelper $helper */
        $helper = pluginApp(AlkimAmazonLoginAndPayHelper::class);
        $html   = ''; //
        if ($helper->getFromSession('paymentWarningTimeout')) {
            $html = 'Ihre Zahlung mit Amazon Pay ist derzeit noch in Prüfung. Bitte beachten Sie, dass wir uns mit Ihnen in Kürze per E-Mail in Verbindung setzen werden, falls noch Unklarheiten bestehen sollten.';
            $helper->setToSession('paymentWarningTimeout', 0);
        }
        $helper->resetSession();

        return $twig->render('AmazonLoginAndPay::content.custom-output', [
            'output'  => '<input type="hidden" name="amazon-pay-action" value="logout" />',
            'warning' => $html,
            'error'   => ''
        ]);
    }
}