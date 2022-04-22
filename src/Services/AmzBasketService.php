<?php //strict

namespace AmazonLoginAndPay\Services;

use AmazonLoginAndPay\Helpers\AlkimAmazonLoginAndPayHelper;
use Plenty\Modules\Basket\Contracts\BasketItemRepositoryContract;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Basket\Models\Basket;
use Plenty\Modules\Basket\Models\BasketItem;
use Plenty\Modules\Item\Item\Contracts\ItemRepositoryContract;
use Plenty\Modules\Item\ItemImage\Contracts\ItemImageRepositoryContract;
use Plenty\Modules\Item\Variation\Contracts\VariationRepositoryContract;

/**
 * Class BasketService
 */
class AmzBasketService
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
     * @param BasketItemRepositoryContract $basketItemRepository
     * @param AlkimAmazonLoginAndPayHelper $helper
     * @param VariationRepositoryContract $variationRepository
     * @param ItemRepositoryContract $itemRepository
     * @param ItemImageRepositoryContract $itemImageRepository
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
        /** @var BasketRepositoryContract $basketRepository */
        $basketRepository = pluginApp(BasketRepositoryContract::class);

        return $basketRepository->load();
    }

    public function getBasketItemsForTemplate(): array
    {
        /** @var \IO\Services\BasketService $basketServiceOriginal */
        $basketServiceOriginal = pluginApp(\IO\Services\BasketService::class);

        return $basketServiceOriginal->getBasketItems();
    }

    public function removeBasketItem($basketItemId)
    {
        /** @var BasketItemRepositoryContract $basketItemRepository */
        $basketItemRepository = pluginApp(BasketItemRepositoryContract::class);
        return $basketItemRepository->removeBasketItem($basketItemId);
    }

    /**
     * List the basket items
     * @return array
     */
    public function getBasketItems(): array
    {

        /** @var BasketItem[] $basketItems */
        $basketItems = $this->basketItemRepository->all();
        $this->helper->log(__CLASS__, __METHOD__, 'basket items', ['test' => $basketItems]);
        $return = [];
        foreach ($basketItems as $basketItem) {
            $item                = $basketItem->toArray();
            $item["final_price"] = $item["price"] * $item["quantity"];
            $this->helper->log(__CLASS__, __METHOD__, 'basket items details pre', $item);
            $variationData = $this->variationRepository->show($item["variationId"], ['images', 'texts', 'variationProperties'], 'de');

            $itemData  = $this->itemRepository->show($item["itemId"]);
            $imageData = $this->itemImageRepository->findByVariationId($item["variationId"]);

            //$itemImageData = $this->itemImageRepository->findByItemId($item["itemId"]);
            $this->helper->log(__CLASS__, __METHOD__, 'basket item details', [
                'itemData'      => $itemData,
                'variationData' => $variationData,
                'imageData'     => $imageData,
                'image'         => $imageData[0],
                'preview'       => $imageData[0]['urlPreview'],
                'is_object'     => is_object($imageData[0])

            ]);
            $item["image"] = '';
            if (!empty($imageData) && isset($imageData[0]) && is_object($imageData[0])) {
                $item["image"] = $imageData[0]->urlPreview;
            }
            $item["name"] = $itemData["texts"][0]["name1"];
            $return[]     = $item;
        }
        $this->helper->log(__CLASS__, __METHOD__, 'basket items return', $return);

        return $return;
    }

    public function getBasketItem(int $basketItemId): array
    {
        $basketItem = $this->basketItemRepository->findOneById($basketItemId);
        if ($basketItem === null) {
            return [];
        }
        $this->helper->log(__CLASS__, __METHOD__, 'basket item details', $basketItem);

        return $basketItem->toArray();
    }

}