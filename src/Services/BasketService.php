<?php //strict

namespace AmazonLoginAndPay\Services;

use AmazonLoginAndPay\Helpers\AlkimAmazonLoginAndPayHelper;
use Plenty\Modules\Basket\Contracts\BasketItemRepositoryContract;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Basket\Models\Basket;
use Plenty\Modules\Item\Item\Contracts\ItemRepositoryContract;
use Plenty\Modules\Item\ItemImage\Contracts\ItemImageRepositoryContract;
use Plenty\Modules\Item\Variation\Contracts\VariationRepositoryContract;

/**
 * Class BasketService
 */
class BasketService
{
    /**
     * @var BasketItemRepositoryContract
     */
    private $basketItemRepository;
    private $helper;
    private $variationRepository;
    private $itemRepository;
    private $itemImageRepository;

    /**
     * BasketService constructor.
     *
     * @param \Plenty\Modules\Basket\Contracts\BasketItemRepositoryContract $basketItemRepository
     * @param \AmazonLoginAndPay\Helpers\AlkimAmazonLoginAndPayHelper $helper
     * @param \Plenty\Modules\Item\Variation\Contracts\VariationRepositoryContract $variationRepository
     * @param \Plenty\Modules\Item\Item\Contracts\ItemRepositoryContract $itemRepository
     * @param \Plenty\Modules\Item\ItemImage\Contracts\ItemImageRepositoryContract $itemImageRepository
     */
    public function __construct(BasketItemRepositoryContract $basketItemRepository, AlkimAmazonLoginAndPayHelper $helper, VariationRepositoryContract $variationRepository, ItemRepositoryContract $itemRepository, ItemImageRepositoryContract $itemImageRepository)
    {
        $this->basketItemRepository = $basketItemRepository;
        $this->helper               = $helper;
        $this->variationRepository  = $variationRepository;
        $this->itemRepository       = $itemRepository;
        $this->itemImageRepository  = $itemImageRepository;
    }

    /**
     * Return the basket as an array
     * @return Basket
     */
    public function getBasket(): Basket
    {
        return pluginApp(BasketRepositoryContract::class)->load();
    }

    /**
     * List the basket items
     * @return \Plenty\Modules\Basket\Models\BasketItem[]
     */
    public function getBasketItems(): array
    {
        $basketItems = $this->basketItemRepository->all();
        $this->helper->log(__CLASS__, __METHOD__, 'basket items', $basketItems);
        $return = [];
        foreach ($basketItems as $basketItem) {
            $item                = $basketItem->toArray();
            $item["final_price"] = $item["price"] * $item["quantity"];
            $this->helper->log(__CLASS__, __METHOD__, 'basket items details pre', $item);
            $variationData = $this->variationRepository->show($item["variationId"], ['images', 'texts'], 'de');
            $itemData      = $this->itemRepository->show($item["itemId"]);
            $imageData     = $this->itemImageRepository->findByVariationId($item["variationId"]);
            //$itemImageData = $this->itemImageRepository->findByItemId($item["itemId"]);
            $this->helper->log(__CLASS__, __METHOD__, 'basket item details', [$itemData, $variationData, $imageData, $itemData[""]]);
            $item["name"] = $itemData["texts"][0]["name1"];
            $return[]     = $item;
        }
        $this->helper->log(__CLASS__, __METHOD__, 'basket items return', $return);

        return $return;
    }

}