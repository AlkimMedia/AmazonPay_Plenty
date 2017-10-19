<?php

namespace AmazonLoginAndPay\Methods;

use Plenty\Modules\Payment\Method\Contracts\PaymentMethodService;
use Plenty\Plugin\ConfigRepository;

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
     * @param ConfigRepository $configRepository
     * @return bool
     */
    public function isActive(ConfigRepository $configRepository)
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
