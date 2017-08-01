<?php //strict

namespace AmazonLoginAndPay\Services;

use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Order\Shipping\Contracts\ParcelServicePresetRepositoryContract;


class AmzCheckoutService
{

    /**
     * @var AmzCustomerService
     */
    private $customerService;
    private $basketRepository;

    /**
     * CheckoutService constructor.
     * @param AmzCustomerService $customerService
     * @param BasketRepositoryContract $basketRepository
     */
    public function __construct(
        AmzCustomerService $customerService,
        BasketRepositoryContract $basketRepository
    )
    {
        $this->customerService = $customerService;
        $this->basketRepository = $basketRepository;
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
        $contact = $this->customerService->getContact();
        return pluginApp(ParcelServicePresetRepositoryContract::class)->getLastWeightedPresetCombinations($this->basketRepository->load(), $contact->classId);
    }

}