<?php

namespace AmazonLoginAndPay\Services;

use AmazonLoginAndPay\Helpers\AlkimAmazonLoginAndPayHelper;
use IO\Services\WebstoreConfigurationService;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Frontend\Contracts\Checkout;
use Plenty\Modules\Order\Shipping\Contracts\ParcelServicePresetRepositoryContract;
use Plenty\Plugin\Application;

class AmzCheckoutService
{
    /**
     * @var AmzCustomerService
     */
    private $customerService;
    private $basketRepository;
    private $helper;
    private $checkout;

    /**
     * CheckoutService constructor.
     *
     * @param AmzCustomerService $customerService
     * @param BasketRepositoryContract $basketRepository
     * @param AlkimAmazonLoginAndPayHelper $helper
     * @param Checkout $checkout
     */
    public function __construct(
        AmzCustomerService $customerService,
        BasketRepositoryContract $basketRepository,
        AlkimAmazonLoginAndPayHelper $helper,
        Checkout $checkout
    ) {
        $this->customerService  = $customerService;
        $this->basketRepository = $basketRepository;
        $this->helper           = $helper;
        $this->checkout         = $checkout;
    }

    /**
     * Get the relevant data for the checkout
     * @return array
     */
    public function getCheckout(): array
    {
        return [
            "shippingProfileList" => $this->getShippingProfileList()
        ];
    }

    public function getShippingProfileList()
    {
        $params                = [
            'countryId'  => $this->getShippingCountryId(),
            'webstoreId' => pluginApp(Application::class)->getWebstoreId(),
        ];
        $accountContactClassId = $this->helper->session->getCustomer()->accountContactClassId;
        /** @var ParcelServicePresetRepositoryContract $repo */
        $repo = pluginApp(ParcelServicePresetRepositoryContract::class);

        return $repo->getLastWeightedPresetCombinations($this->basketRepository->load(), $accountContactClassId, $params);
    }

    public function getShippingCountryId()
    {
        $currentShippingCountryId = (int)$this->checkout->getShippingCountryId();
        if ($currentShippingCountryId <= 0) {
            return pluginApp(WebstoreConfigurationService::class)->getDefaultShippingCountryId();
        }

        return $currentShippingCountryId;
    }
}