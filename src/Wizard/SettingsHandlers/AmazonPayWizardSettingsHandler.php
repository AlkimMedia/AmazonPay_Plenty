<?php

namespace AmazonLoginAndPay\Wizard\SettingsHandlers;
//


use AmazonLoginAndPay\Helpers\AlkimAmazonLoginAndPayHelper;
use Plenty\Modules\ContentCache\Contracts\ContentCacheInvalidationRepositoryContract;
use Plenty\Modules\Order\Currency\Contracts\CurrencyRepositoryContract;
use Plenty\Modules\Order\Shipping\Countries\Contracts\CountryRepositoryContract;
use Plenty\Modules\Plugin\Contracts\ConfigurationRepositoryContract;
use Plenty\Modules\Plugin\PluginSet\Contracts\PluginSetRepositoryContract;
use Plenty\Modules\Plugin\PluginSet\Models\PluginSetEntry;

use Plenty\Modules\Wizard\Contracts\WizardSettingsHandler;

/**
 * Class ShopWizardSettingsHandler
 * @package Ceres\Wizard\ShopWizard\SettingsHandlers
 */
class AmazonPayWizardSettingsHandler implements WizardSettingsHandler
{
    /**
     * @var CountryRepositoryContract
     */
    private $countryRepository;

    /**
     * @var CurrencyRepositoryContract
     */
    private $currencyRepository;

    /**
     * ShopWizardSettingsHandler constructor.
     *
     * @param CountryRepositoryContract $countryRepository
     * @param CurrencyRepositoryContract $currencyRepositoryContract
     */
    public function __construct(CountryRepositoryContract $countryRepository, CurrencyRepositoryContract $currencyRepositoryContract)
    {
        $this->countryRepository  = $countryRepository;
        $this->currencyRepository = $currencyRepositoryContract;
    }

    /**
     * @param array $parameters
     *
     * @return bool
     */
    public function handle(array $parameters): bool
    {
        $data        = $parameters['data'];
        $pluginSetId = (int)$data['pluginSetId'];

        /** @var \Plenty\Modules\Plugin\Contracts\ConfigurationRepositoryContract $configRepo */
        $configRepo = pluginApp(ConfigurationRepositoryContract::class);
        /** @var \Plenty\Modules\Plugin\PluginSet\Contracts\PluginSetRepositoryContract $pluginSetRepo */
        $pluginSetRepo = pluginApp(PluginSetRepositoryContract::class);
        $pluginSets    = $pluginSetRepo->list();
        $pluginId      = '';
        /** @var \AmazonLoginAndPay\Helpers\AlkimAmazonLoginAndPayHelper $helper */
        $helper = pluginApp(AlkimAmazonLoginAndPayHelper::class);
        $helper->log(__CLASS__, __METHOD__, 'save settings - plugin sets', [$pluginSets, $pluginSetId, $parameters]);

        if (count($pluginSets)) {
            foreach ($pluginSets as $pluginSet) {
                $helper->log(__CLASS__, __METHOD__, 'save settings - plugin set entries', [$pluginSet->pluginSetEntries]);
                foreach ($pluginSet->pluginSetEntries as $pluginSetEntry) {
                    $helper->log(__CLASS__, __METHOD__, 'save settings - plugin set entry plugin name', [$pluginSetEntry->plugin->name]);
                    if ($pluginSetEntry instanceof PluginSetEntry && $pluginSetEntry->plugin->name === 'AmazonLoginAndPay' && $pluginSetEntry->pluginSetId == $pluginSetId) {
                        $pluginId = (int)$pluginSetEntry->pluginId;
                    }
                }
            }
        }

        $helper->log(__CLASS__, __METHOD__, 'save settings - plugin id', [$pluginId]);

        if (count($data)) {
            $configData = [];
            foreach ($data as $itemKey => $itemVal) {
                $configData[] = [
                    'key'   => $itemKey,
                    'value' => $itemVal
                ];
            }
            $helper->log(__CLASS__, __METHOD__, 'save settings - config data', [$configData]);
            $configRepo->saveConfiguration($pluginId, $configData, $pluginSetId);
        }

        //invalidate caching
        /** @var \Plenty\Modules\ContentCache\Contracts\ContentCacheInvalidationRepositoryContract $cacheInvalidRepo */
        $cacheInvalidRepo = pluginApp(ContentCacheInvalidationRepositoryContract::class);
        $cacheInvalidRepo->invalidateAll();

        return true;
    }

}
