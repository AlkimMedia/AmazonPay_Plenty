<?php

namespace AmazonLoginAndPay\Methods;

use Plenty\Modules\Payment\Method\Contracts\PaymentMethodService;

/**
 * Class AmzPaymentMethod
 * @package AmazonLoginAndPay\Methods
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

    public function isBackendSearchable(): bool
    {
        return true;
    }

    public function isBackendActive(): bool
    {
        return true;
    }

    public function getBackendName(string $lang): string
    {
        return $this->getName();
    }

    public function canHandleSubscriptions(): bool
    {
        return false;
    }
}