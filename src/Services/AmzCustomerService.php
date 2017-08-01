<?php

namespace AmazonLoginAndPay\Services;


use Plenty\Modules\Account\Contact\Contracts\ContactRepositoryContract;
use Plenty\Modules\Account\Contact\Models\Contact;

/**
 * Class AmzCustomerService
 */
class AmzCustomerService
{
    private $contactRepository;
    //private $accountService;

    /**
     * CustomerService constructor.
     * @param ContactRepositoryContract $contactRepository
     */
    public function __construct(
        ContactRepositoryContract $contactRepository
    )
    {
        $this->contactRepository = $contactRepository;
    }

    /**
     * Find the current contact by ID
     * @return null|Contact
     */
    public function getContact()
    {
        if ($this->getContactId() > 0) {
            return $this->contactRepository->findContactById($this->getContactId());
        }
        return null;
    }

    /**
     * Get the ID of the current contact from the session
     * @return int
     */
    public function getContactId(): int
    {
        return 0;//$this->accountService->getAccountContactId();
    }
}