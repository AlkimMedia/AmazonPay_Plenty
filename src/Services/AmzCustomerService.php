<?php

namespace AmazonLoginAndPay\Services;

use AmazonLoginAndPay\Helpers\AlkimAmazonLoginAndPayHelper;
use Exception;
use Plenty\Modules\Account\Contact\Contracts\ContactRepositoryContract;
use Plenty\Modules\Account\Contact\Models\Contact;
use Plenty\Modules\Authentication\Contracts\ContactAuthenticationRepositoryContract;
use Plenty\Modules\Frontend\Services\AccountService;
use Plenty\Plugin\ExternalAuth\Contracts\ExternalAccessRepositoryContract;
use Plenty\Plugin\ExternalAuth\Services\ExternalAuthService;

/**
 * Class AmzCustomerService
 */
class AmzCustomerService
{
    const EXTERNAL_AUTH_SLUG = 'Amazon';
    private $contactRepository;
    private $contactAuthenticationRepository;
    /** @var AccountService $accountService */
    private $accountService;
    /** @var ExternalAccessRepositoryContract $externalAccessRepository */
    private $externalAccessRepository;
    /** @var ExternalAuthService $externalAuthService */
    private $externalAuthService;

    /**
     * CustomerService constructor.
     *
     * @param ContactRepositoryContract $contactRepository
     * @param ContactAuthenticationRepositoryContract $contactAuthenticationRepository
     */
    public function __construct(
        ContactRepositoryContract $contactRepository,
        ContactAuthenticationRepositoryContract $contactAuthenticationRepository

    ) {
        $this->contactRepository               = $contactRepository;
        $this->contactAuthenticationRepository = $contactAuthenticationRepository;

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
    public function getContactId()
    {
        $this->init();

        return $this->accountService->getAccountContactId();
    }

    /**
     *
     */
    public function init()
    {
        $this->accountService           = pluginApp(AccountService::class);
        $this->externalAccessRepository = pluginApp(ExternalAccessRepositoryContract::class);
        $this->externalAuthService      = pluginApp(ExternalAuthService::class);
    }

    public function connectAccounts($data, $email, $password)
    {
        $helper = pluginApp(AlkimAmazonLoginAndPayHelper::class);
        $helper->log(__CLASS__, __METHOD__, 'connectAccounts', [$data, $email, $password]);

        return $this->loginWithAmazonUserData($data, $email, $password);
    }

    public function loginWithAmazonUserData($data, $connectEmail = null, $connectPassword = null, $onlyIfExisting = false)
    {
        $this->init();
        /** @var AlkimAmazonLoginAndPayHelper $helper */
        $helper = pluginApp(AlkimAmazonLoginAndPayHelper::class);
        $helper->log(__CLASS__, __METHOD__, 'start login with amazon + optional account connect', ['data' => $data, 'email' => $connectEmail, 'password' => $connectPassword]);

        $return = [
            'success' => false
        ];

        $email        = $data["email"];
        $name         = $data["name"];
        $amazonUserId = $data["user_id"];
        if (!empty($amazonUserId) && !empty($email)) {
            $doLogin            = false;
            $externalAccessInfo = null;
            try {
                $externalAccessInfo = $this->externalAccessRepository->findForTypeAndExternalId(self::EXTERNAL_AUTH_SLUG, $amazonUserId);
            } catch (Exception $e) {
                $helper->log(__CLASS__, __METHOD__, 'no external access info received', [$e, $e->getMessage()]);
            }
            if (!is_object($externalAccessInfo) || empty($externalAccessInfo->contactId)) {
                if (!$onlyIfExisting) {
                    $contactIdByEmail = $this->getContactIdByEmail($email);
                    if (empty($contactIdByEmail)) {
                        $contactData = [
                            'typeId'     => 1,
                            'fullName'   => $name,
                            'email'      => $email,
                            'referrerId' => 1,
                            'options'    => [
                                [
                                    'typeId'    => 2,
                                    'subTypeId' => 4,
                                    'value'     => $email,
                                    'priority'  => 0
                                ],
                                [
                                    'typeId'    => 8,
                                    'subTypeId' => 4,
                                    'value'     => $name,
                                    'priority'  => 0
                                ]
                            ]
                        ];

                        $contact = $this->contactRepository->createContact($contactData);
                        $helper->log(__CLASS__, __METHOD__, 'contact created', [$contact, $contactData]);
                        $contactId                 = $contact->id;
                        $externalAccessCreatedInfo = $this->externalAccessRepository->create([
                            'contactId'         => $contactId,
                            'accessType'        => self::EXTERNAL_AUTH_SLUG,
                            'externalContactId' => $amazonUserId,
                        ]);
                        $helper->log(__CLASS__, __METHOD__, 'external access created', [$externalAccessCreatedInfo]);
                        $doLogin = true;
                    } else {
                        if (!empty($connectEmail) && !empty($connectPassword)) {
                            $loginResult = $this->contactAuthenticationRepository->authenticateWithContactEmail($connectEmail, $connectPassword);
                            $helper->log(__CLASS__, __METHOD__, 'login result', ['result' => $loginResult, 'contact_id' => $this->getContactId()]);
                            if ($this->getContactId() == $contactIdByEmail && $connectEmail == $email) {
                                $externalAccessCreatedInfo = $this->externalAccessRepository->create([
                                    'contactId'         => $contactIdByEmail,
                                    'accessType'        => self::EXTERNAL_AUTH_SLUG,
                                    'externalContactId' => $amazonUserId,
                                ]);
                                $helper->log(__CLASS__, __METHOD__, 'external access created', [$externalAccessCreatedInfo]);
                                $return["success"] = true;
                            } else {
                                $helper->log(__CLASS__, __METHOD__, 'wrong credentials for account connect', [], true);
                            }
                        } else {
                            $return["redirect"] = '/amazon-connect-accounts';
                        }
                    }
                }
            } else {
                $doLogin = true;
            }

            if ($doLogin) {
                $loginResult = $this->externalAuthService->logInWithExternalUserId($amazonUserId, self::EXTERNAL_AUTH_SLUG);
                $helper->log(__CLASS__, __METHOD__, 'login completed', [$loginResult]);
                $return["success"] = true;
            }
        } else {
            $helper->log(__CLASS__, __METHOD__, 'no amazon user data given for login', ['data' => $data], true);
        }
        $helper->log(__CLASS__, __METHOD__, 'return value', $return);

        return $return;
    }

    public function getContactIdByEmail($email)
    {
        return $this->contactRepository->getContactIdByEmail($email);
    }

}