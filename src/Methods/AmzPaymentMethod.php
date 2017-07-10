<?php

namespace AmazonLoginAndPay\Methods;

use /** @noinspection PhpUndefinedNamespaceInspection */
    Plenty\Plugin\ConfigRepository;
use /** @noinspection PhpUndefinedNamespaceInspection */
    Plenty\Modules\Payment\Method\Contracts\PaymentMethodService;
use /** @noinspection PhpUndefinedNamespaceInspection */
    Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use /** @noinspection PhpUndefinedNamespaceInspection */
    Plenty\Modules\Basket\Models\Basket;

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
     * @param BasketRepositoryContract $basketRepositoryContract
     * @return bool
     */
    public function isActive(ConfigRepository $configRepository,
                             BasketRepositoryContract $basketRepositoryContract)
    {
        /** @var bool $active */
        $active = true;

        /** @var Basket $basket */
        $basket = $basketRepositoryContract->load();

        /**
         * Check the shipping profile ID. The ID can be entered in the config.json.
         */
        if ($configRepository->get('AmazonLoginAndPay.status') == 0) {
            $active = false;
        }

        return $active;
    }

    /**
     * Get the name of the payment method. The name can be entered in the config.json.
     *
     * @param ConfigRepository $configRepository
     * @return string
     */
    public function getName(ConfigRepository $configRepository)
    {
        return 'Amazon Pay';

    }

    /**
     * Get the path of the icon. The URL can be entered in the config.json.
     *
     * @param ConfigRepository $configRepository
     * @return string
     */
    public function getIcon(ConfigRepository $configRepository)
    {
        return '';
    }

    /**
     * Get the description of the payment method. The description can be entered in the config.json.
     *
     * @param ConfigRepository $configRepository
     * @return string
     */
    public function getDescription(ConfigRepository $configRepository)
    {
        return '';
    }
}