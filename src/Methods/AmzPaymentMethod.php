<?php

namespace AmazonLoginAndPay\Methods;

use Plenty\Modules\Payment\Method\Contracts\PaymentMethodService;

/**
 * Class PayUponPickupPaymentMethod
 * @package PayUponPickup\Methods
 */
class AmzPaymentMethod extends PaymentMethodService
{
    /**
     * Check the configuration if the payment method is active
     * Return true if the payment method is active, else return false
     *
     * @return bool
     */
    public function isActive()
    {
        return false;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'Amazon Pay';

    }

    /**
     * @return string
     */
    public function getIcon()
    {
        return '';
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return '';
    }

    public function isExpressCheckout()
    {
        return true;
    }
}