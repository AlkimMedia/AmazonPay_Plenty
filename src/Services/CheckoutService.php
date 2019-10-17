<?php //strict

namespace AmazonLoginAndPay\Services;

use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Order\Shipping\Contracts\ParcelServicePresetRepositoryContract;

/**
 * Class CheckoutService
 * @package IO\Services
 */
class CheckoutService
{

    /**
     * @var CustomerService
     */
    private $customerService;
    private $basketRepository;

    /**
     * CheckoutService constructor.
     *
     * @param CustomerService $customerService
     * @param BasketRepositoryContract $basketRepository
     */
    public function __construct(
        CustomerService $customerService,
        BasketRepositoryContract $basketRepository
    ) {
        $this->customerService  = $customerService;
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