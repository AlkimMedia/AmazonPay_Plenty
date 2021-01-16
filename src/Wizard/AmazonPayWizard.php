<?php

namespace AmazonLoginAndPay\Wizard;

use AmazonLoginAndPay\Helpers\AlkimAmazonLoginAndPayHelper;
use AmazonLoginAndPay\Wizard\Services\ConfigService;
use AmazonLoginAndPay\Wizard\Services\StepBuilderService;
use Plenty\Modules\Wizard\Services\WizardProvider;
use Plenty\Plugin\Translation\Translator;

/**
 * Class ShopWizard
 */
class AmazonPayWizard extends WizardProvider
{

    /**
     * @var Translator
     */
    private $translator;
    private $configService;

    /**
     * ShopWizard constructor.
     *
     * @param \AmazonLoginAndPay\Wizard\Services\ConfigService $configService
     * @param Translator $translator
     */
    public function __construct(ConfigService $configService, Translator $translator)
    {
        $this->configService = $configService;
        $this->translator    = $translator;
    }

    /**
     * @return array
     */
    protected function structure(): array
    {
        /** @var \AmazonLoginAndPay\Wizard\Services\StepBuilderService $stepBuilder */
        $stepBuilder = pluginApp(StepBuilderService::class);

        $return = [
            "title"                => "Wizard.title",
            "shortDescription"     => "Wizard.shortDescription",
            "keywords"             => ['Amazon Pay'],
            "topics"               => [
                "payment"
            ],
            "key"                  => "amazonPay-assistant",
            "reloadStructure"      => true,
            "iconPath"             => 'https://m.media-amazon.com/images/G/01/EPSMarketingJRubyWebsite/assets/mindstorms/amazonpay-logo-rgb_clr._CB1560911315_.svg',
            //'dataSource' => 'Ceres\Wizard\ShopWizard\DataSource\ShopWizardDataSource',
            'settingsHandlerClass' => 'AmazonLoginAndPay\Wizard\SettingsHandlers\AmazonPayWizardSettingsHandler',
            //'dependencyClass' => 'Ceres\Wizard\ShopWizard\DynamicLoaders\ShopWizardDynamicLoader',
            "translationNamespace" => "AmazonLoginAndPay",
            "options"              => [
                'pluginSetId' => $this->buildPluginSetOptions()
            ],
            "steps"                => [
                "credentialsStep" => $stepBuilder->generateCredentialsStep(),
                "debugStep" => $stepBuilder->generateDebugStep(),
                "processesStep" => $stepBuilder->generateProcessesStep(),
                "styleStep" => $stepBuilder->generateStyleStep()
            ]
        ];
        /** @var \AmazonLoginAndPay\Helpers\AlkimAmazonLoginAndPayHelper $helper */
        $helper = pluginApp(AlkimAmazonLoginAndPayHelper::class);
        $helper->log(__CLASS__, __METHOD__, 'wizard structure', [$return]);
        return $return;
    }

    /**
     * @return array
     */
    private function buildPluginSetOptions()
    {
        $pluginSets      = $this->configService->getPluginSets();
        $pluginSetValues = [
            [
                "value"   => "",
                "caption" => ""
            ]

        ];

        if (count($pluginSets)) {
            foreach ($pluginSets as $pluginSet) {
                $pluginSetValues[] = [
                    "value"   => $pluginSet['id'],
                    "caption" => $pluginSet['name']
                ];
            }
        }

        return [
            'type'         => 'select',
            'defaultValue' => $pluginSetValues[0]['value'],
            'options'      => [
                'name'          => 'Wizard.pluginSetSelection',
                'listBoxValues' => $pluginSetValues
            ]
        ];
    }
}
