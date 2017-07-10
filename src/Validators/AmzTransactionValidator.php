<?php
namespace AmazonLoginAndPay\Validators;

use /** @noinspection PhpUndefinedNamespaceInspection */
    Plenty\Validation\Validator;

/**
 *  Validator Class
 */
class AmzTransactionValidator extends Validator
{
    protected function defineAttributes()
    {
        $this->addString('orderReference', true);
    }
}

