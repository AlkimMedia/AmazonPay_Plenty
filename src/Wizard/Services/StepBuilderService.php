<?php

namespace AmazonLoginAndPay\Wizard\Services;

/**
 * Class RequiredSettingsStep
 * @package Ceres\Wizard\ShopWizard\Steps\Builder
 */
class StepBuilderService
{
    /**
     * @return array
     */
    public function generateCredentialsStep(): array
    {

        return [
            "title"           => "Wizard.credentialsStepTitle",
            "description"     => "Wizard.credentialsStepDescription",
            "condition"       => true,
            "validationClass" => "AmazonLoginAndPay\Wizard\Validators\CredentialsValidator",
            "sections"        => [
                [
                    "title"       => "Wizard.credentialsSectionTitle",
                    "description" => "Wizard.credentialsSectionDescription",
                    "form"        => [
                        "merchantId"         => [
                            "type"    => "text",
                            "options" => [
                                "name"     => "Config.merchantIdLabel",
                                "required" => true
                            ]
                        ],
                        "mwsAccessKey"       => [
                            "type"    => "text",
                            "options" => [
                                "name"     => "Config.mwsAccessKeyLabel",
                                "required" => true
                            ]
                        ],
                        "mwsSecretAccessKey" => [
                            "type"    => "text",
                            "options" => [
                                "name"     => "Config.mwsSecretAccessKeyLabel",
                                "required" => true
                            ]
                        ],
                        "loginClientId"      => [
                            "type"    => "text",
                            "options" => [
                                "name"     => "Config.loginClientIdLabel",
                                "required" => true
                            ]
                        ],
                        "accountCountry"     => [
                            "type"    => "select",
                            "options" => [
                                "name"          => "Config.accountCountryLabel",
                                "listBoxValues" => [
                                    [
                                        "value"   => "DE",
                                        "caption" => "Config.accountCountryPossibleValueDE"
                                    ],
                                    [
                                        "value"   => "UK",
                                        "caption" => "Config.accountCountryPossibleValueUK"
                                    ],
                                    [
                                        "value"   => "US",
                                        "caption" => "Config.accountCountryPossibleValueUS"
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    public function generateDebugStep(): array
    {

        return [
            "title"       => "Wizard.debugStepTitle",
            "description" => "Wizard.debugStepDescription",
            "condition"   => true,
            "sections"    => [
                [
                    "title" => "Wizard.debugSectionTitle",
                    "form"  => [
                        "sandbox"     => [
                            "type"    => "checkbox",
                            "options" => [
                                "name" => "Config.sandboxLabel"
                            ]
                        ],
                        "hideButtons" => [
                            "type"    => "checkbox",
                            "options" => [
                                "name" => "Config.hideButtonsLabel"
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    public function generateProcessesStep(): array
    {

        return [
            "title"       => "Wizard.processesStepTitle",
            "description" => "Wizard.processesStepDescription",
            "condition"   => true,
            "sections"    => [
                [
                    "title" => "Wizard.authCaptureTypeSectionTitle",
                    "form"  => [
                        "authorizationMode" => [
                            "type"    => "select",
                            "options" => [
                                "name"          => "Config.authorizationModeLabel",
                                "listBoxValues" => [
                                    [
                                        "value"   => "default",
                                        "caption" => "Config.authorizationModePossibleValueDefault"
                                    ],
                                    [
                                        "value"   => "fast_auth",
                                        "caption" => "Config.authorizationModePossibleValueFastAuth"
                                    ],
                                    [
                                        "value"   => "manually",
                                        "caption" => "Config.authorizationModePossibleValueManually"
                                    ]
                                ]
                            ]

                        ],
                        "captureMode"       => [
                            "type"    => "select",
                            "options" => [
                                "name"          => "Config.captureModeLabel",
                                "listBoxValues" => [
                                    [
                                        "value"   => "manually",
                                        "caption" => "Config.captureModePossibleValueManually"
                                    ],
                                    [
                                        "value"   => "after_auth",
                                        "caption" => "Config.captureModePossibleValueAfterAuth"
                                    ]
                                ]
                            ]

                        ]
                    ]
                ],
                [
                    "title" => "Wizard.statusSectionTitle",
                    "form"  => [
                        "authorizedStatus"  => [
                            "type"         => "text",
                            "defaultValue" => "5.0",
                            "options"      => [
                                "name" => "Config.authorizedStatusLabel"
                            ]
                        ],
                        "softDeclineStatus" => [
                            "type"    => "text",
                            "options" => [
                                "name" => "Config.softDeclineStatusLabel"
                            ]
                        ],
                        "hardDeclineStatus" => [
                            "type"    => "text",
                            "options" => [
                                "name" => "Config.hardDeclineStatusLabel"
                            ]
                        ],
                    ]
                ],
                [
                    "title" => "Wizard.miscSectionTitle",
                    "form"  => [
                        "closeOroOnCompleteCapture" => [
                            "type"         => "checkbox",
                            "defaultValue" => true,
                            "options"      => [
                                "name" => "Config.closeOroOnCompleteCaptureLabel"
                            ]
                        ],
                        "useEmailInShippingAddress" => [
                            "type"         => "checkbox",
                            "defaultValue" => true,
                            "options"      => [
                                "name" => "Config.useEmailInShippingAddressLabel"
                            ]
                        ],
                        "amzShopwareConnectorKey"   => [
                            "type"    => "text",
                            "options" => [
                                "name" => "Config.shopwareConnectorKeyLabel"
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    public function generateStyleStep(): array
    {

        return [
            "title"     => "Wizard.styleStepTitle",
            "condition" => true,
            "sections"  => [
                [
                    "title" => "Wizard.buttonsSectionTitle",
                    "form"  => [
                        "payButtonSize"  => [
                            "type"    => "select",
                            "options" => [
                                "name"          => "Config.payButtonSizeLabel",
                                "listBoxValues" => [
                                    [
                                        "value"   => "small",
                                        "caption" => "Config.payButtonSizePossibleValueSmall"
                                    ],
                                    [
                                        "value"   => "medium",
                                        "caption" => "Config.payButtonSizePossibleValueMedium"
                                    ],
                                    [
                                        "value"   => "large",
                                        "caption" => "Config.payButtonSizePossibleValueLarge"
                                    ],
                                    [
                                        "value"   => "x-large",
                                        "caption" => "Config.payButtonSizePossibleValueXLarge"
                                    ]
                                ]
                            ]

                        ],
                        "payButtonColor" => [
                            "type"    => "select",
                            "options" => [
                                "name"          => "Config.payButtonColorLabel",
                                "listBoxValues" => [
                                    [
                                        "value"   => "Gold",
                                        "caption" => "Config.payButtonColorPossibleValueGold"
                                    ],
                                    [
                                        "value"   => "LightGray",
                                        "caption" => "Config.payButtonColorPossibleValueLightGray"
                                    ],
                                    [
                                        "value"   => "DarkGray",
                                        "caption" => "Config.payButtonColorPossibleValueDarkGray"
                                    ]
                                ]
                            ]

                        ],
                        "loginButtonSize"  => [
                            "type"    => "select",
                            "options" => [
                                "name"          => "Config.loginButtonSizeLabel",
                                "listBoxValues" => [
                                    [
                                        "value"   => "small",
                                        "caption" => "Config.payButtonSizePossibleValueSmall"
                                    ],
                                    [
                                        "value"   => "medium",
                                        "caption" => "Config.payButtonSizePossibleValueMedium"
                                    ],
                                    [
                                        "value"   => "large",
                                        "caption" => "Config.payButtonSizePossibleValueLarge"
                                    ],
                                    [
                                        "value"   => "x-large",
                                        "caption" => "Config.payButtonSizePossibleValueXLarge"
                                    ]
                                ]
                            ]

                        ],
                        "loginButtonColor" => [
                            "type"    => "select",
                            "options" => [
                                "name"          => "Config.loginButtonColorLabel",
                                "listBoxValues" => [
                                    [
                                        "value"   => "Gold",
                                        "caption" => "Config.payButtonColorPossibleValueGold"
                                    ],
                                    [
                                        "value"   => "LightGray",
                                        "caption" => "Config.payButtonColorPossibleValueLightGray"
                                    ],
                                    [
                                        "value"   => "DarkGray",
                                        "caption" => "Config.payButtonColorPossibleValueDarkGray"
                                    ]
                                ]
                            ]

                        ],
                    ]
                ],
                [
                    "title" => "Wizard.popupSectionTitle",
                    "form"  => [
                        "usePopup" => [
                            "type"         => "checkbox",
                            "options"      => [
                                "name" => "Config.usePopupLabel"
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

}
