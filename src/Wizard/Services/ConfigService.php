<?php

namespace AmazonLoginAndPay\Wizard\Services;

use AmazonLoginAndPay\Helpers\AlkimAmazonLoginAndPayHelper;
use Plenty\Modules\Plugin\Contracts\PluginRepositoryContract;
use Plenty\Modules\Plugin\Models\Plugin;
use Plenty\Modules\Plugin\PluginSet\Contracts\PluginSetRepositoryContract;
use Plenty\Modules\Plugin\PluginSet\Models\PluginSet;
use Plenty\Modules\System\Contracts\WebstoreRepositoryContract;

class ConfigService
{
    /**
     * @var array
     */
    private $pluginSetList;
    public function __construct()
    {

    }


    /**
     * @return \Plenty\Modules\System\Models\Webstore[]
     */
    public function getWebStores(): array
    {
        /** @var WebstoreRepositoryContract $webStoreRepo */
        $webStoreRepo = pluginApp(WebstoreRepositoryContract::class);
        $webStores    = [];
        $allWebStores = $webStoreRepo->loadAllPreview();
        if (count($allWebStores)) {
            foreach ($allWebStores as $webStore) {
                $webStores[] = $webStore;
            }
        }
        return $webStores;
    }

    /**
     * @return array
     */
    public function getPluginSets(): array
    {
        if(is_array($this->pluginSetList)) {
            return $this->pluginSetList;
        }
        /** @var \Plenty\Modules\Plugin\PluginSet\Contracts\PluginSetRepositoryContract $pluginSetRepo */
        $pluginSetRepo  = pluginApp(PluginSetRepositoryContract::class);
        /** @var \Plenty\Modules\Plugin\Contracts\PluginRepositoryContract $pluginRepo */
        $pluginRepo     = pluginApp(PluginRepositoryContract::class);
        $pluginSets     = $pluginSetRepo->list();
        $pluginSetsData = $pluginSets->toArray();
        $pluginSetList  = [];
        if (count($pluginSetsData)) {
            $plugin = $pluginRepo->getPluginByName("AmazonLoginAndPay");
            if ($plugin instanceof Plugin) {
                foreach ($pluginSetsData as $pluginSetData) {
                    $pluginSet = $pluginSets->where('id', '=', $pluginSetData['id'])->first();
                    if($pluginSet instanceof PluginSet) {
                        if ($pluginRepo->isActiveInPluginSet($plugin->id, $pluginSet)) {
                            $pluginSetList[] = $pluginSetData;
                        }
                    }
                }
            }
        }
        /** @var \AmazonLoginAndPay\Helpers\AlkimAmazonLoginAndPayHelper $helper */
        $helper = pluginApp(AlkimAmazonLoginAndPayHelper::class);
        $helper->log(__CLASS__, __METHOD__, 'plugin sets', [$pluginSetList]);
        $this->pluginSetList = $pluginSetList;
        return $pluginSetList;
    }

    /**
     * @param $webstoreId
     * @return mixed
     */
    public function getStoreIdentifier($webstoreId)
    {
        /** @var WebstoreRepositoryContract $webStoreRepo */
        $webStoreRepo = pluginApp(WebstoreRepositoryContract::class);
        $store = $webStoreRepo->findById($webstoreId);
        $storeIdentifier = $store->storeIdentifier;
        return $storeIdentifier;
    }
}
