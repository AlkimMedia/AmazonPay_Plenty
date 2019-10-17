<?php

namespace AmazonLoginAndPay\Helpers;

use Plenty\Modules\Payment\Method\Contracts\PaymentMethodRepositoryContract;

class PaymentMethodHelper
{
    /**
     * @var PaymentMethodRepositoryContract
     */
    private $paymentMethodRepository;

    public function __construct(PaymentMethodRepositoryContract $paymentMethodRepository)
    {
        $this->paymentMethodRepository = $paymentMethodRepository;
    }

    public function createMopIfNotExistsAndReturnId()
    {
        $paymentMethodId = $this->getPaymentMethod();
        if ($paymentMethodId === false) {
            $paymentMethodData = [
                'pluginKey'  => 'alkim_amazonpay',
                'paymentKey' => 'AMAZONPAY',
                'name'       => 'Amazon Pay'
            ];

            $this->paymentMethodRepository->createPaymentMethod($paymentMethodData);
            $paymentMethodId = $this->getPaymentMethod();
        }

        return $paymentMethodId;
    }

    public function getPaymentMethod()
    {
        $paymentMethods = $this->paymentMethodRepository->allForPlugin('alkim_amazonpay');
        if (!is_null($paymentMethods)) {
            foreach ($paymentMethods as $paymentMethod) {
                if ($paymentMethod->paymentKey == 'AMAZONPAY') {
                    return $paymentMethod->id;
                }
            }
        }

        return false;
    }
}
