<?php

namespace AmazonLoginAndPay\Services;

use Plenty\Modules\Account\Contact\Contracts\ContactRepositoryContract;
use Plenty\Modules\Frontend\Services\AccountService;

/**
 * Class CustomerService
 * @package IO\Services
 */
class CustomerService
{
    private $contactRepository;
    private $accountService;

    /**
     * CustomerService constructor.
     *
     * @param \Plenty\Modules\Account\Contact\Contracts\ContactRepositoryContract $contactRepository
     * @param \Plenty\Modules\Frontend\Services\AccountService $accountService
     */
    public function __construct(ContactRepositoryContract $contactRepository, AccountService $accountService)
    {
        $this->contactRepository = $contactRepository;
        $this->accountService    = $accountService;
    }

    /**
     * Find the current contact by ID
     * @return null|\Plenty\Modules\Account\Contact\Models\Contact
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
        return $this->accountService->getAccountContactId();
    }
}