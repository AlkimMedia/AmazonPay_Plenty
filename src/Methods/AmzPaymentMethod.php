<?php

namespace AmazonLoginAndPay\Methods;

use Plenty\Modules\Payment\Method\Services\PaymentMethodBaseService;

/**
 * Class AmzPaymentMethod
 * @package AmazonLoginAndPay\Methods
 */
class AmzPaymentMethod extends PaymentMethodBaseService

{
    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return false;
    }

    /**
     * @param string $lang
     *
     * @return string
     */
    public function getIcon(string $lang = ''): string
    {
        return '';
    }

    /**
     * @param string $lang
     *
     * @return string
     */
    public function getDescription(string $lang = ''): string
    {
        return '';
    }

    /**
     * @return float
     */
    public function getFee(): float
    {
        return 0;
    }

    /**
     * @param string $lang
     *
     * @return string
     */
    public function getSourceUrl(string $lang = ''): string
    {
        return '';
    }

    /**
     * @return bool
     */
    public function isSwitchableTo(): bool
    {
        return false;
    }

    /**
     * @return bool
     */
    public function isSwitchableFrom(): bool
    {
        return false;
    }

    /**
     * @return bool
     */
    public function isBackendSearchable(): bool
    {
        return true;
    }

    /**
     * @return bool
     */
    public function isBackendActive(): bool
    {
        return true;
    }

    /**
     * @param string $lang
     *
     * @return string
     */
    public function getBackendName(string $lang = ''): string
    {
        return $this->getName($lang);
    }

    /**
     * @param string $lang
     *
     * @return string
     */
    public function getName(string $lang = ''): string
    {
        return 'Amazon Pay';

    }

    /**
     * @return bool
     */
    public function canHandleSubscriptions(): bool
    {
        return false;
    }

    /**
     * @return string
     */
    public function getBackendIcon(): string
    {
        //TODO
        return 'images/amazon_pay_logo.png';
    }
}
