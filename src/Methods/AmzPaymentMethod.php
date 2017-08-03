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
        /** @var bool $active */
        $active = true;
        /**
         * Check the shipping profile ID. The ID can be entered in the config.json.
         */
        if ($configRepository->get('AmazonLoginAndPay.status') == 'false') {
            $active = false;
        }

        return $active;
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
}