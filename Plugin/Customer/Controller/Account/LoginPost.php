<?php

namespace Amitshree\Customer\Plugin\Customer\Controller\Account;

use Magento\Customer\Model\Session;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface as ScopeConfig;
use Magento\Framework\App\Action\Context;

/**
 * Login post controller plugin
 */
class LoginPost
{

    protected const MODULE_ENABLED = 'customerlogin/general/enable';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var Session
     */
    protected $session;

    /** @var Validator */
    protected $formKeyValidator;

    /** @var CustomerRepositoryInterface */
    protected $customerRepositoryInterface;

    /** @var ManagerInterface **/
    protected $messageManager;

    /**
     * @var \Magento\Customer\Api\Data\CustomerInterface
     */
    protected $currentCustomer;

    /**
     * @var \Magento\Framework\Controller\Result\RedirectFactory
     */
    private $resultRedirectFactory;

    /**
     * @param Session $customerSession
     * @param Validator $formKeyValidator
     * @param CustomerRepositoryInterface $customerRepositoryInterface
     * @param ManagerInterface $messageManager
     * @param ScopeConfig $scopeConfig
     * @param Context $context
     */
    public function __construct(
        Session $customerSession,
        Validator $formKeyValidator,
        CustomerRepositoryInterface $customerRepositoryInterface,
        ManagerInterface $messageManager,
        ScopeConfig $scopeConfig,
        Context $context
    ) {
        $this->session = $customerSession;
        $this->formKeyValidator = $formKeyValidator;
        $this->customerRepositoryInterface = $customerRepositoryInterface;
        $this->messageManager = $messageManager;
        $this->scopeConfig = $scopeConfig;
        $this->resultRedirectFactory = $context->getResultRedirectFactory();
    }

    /**
     * Redirect to login
     * @param \Magento\Customer\Controller\Account\LoginPost $loginPost
     * @param \Closure $proceed
     * @return \Magento\Framework\Controller\Result\Redirect|mixed
     */
    public function aroundExecute(\Magento\Customer\Controller\Account\LoginPost $loginPost, \Closure $proceed)
    {
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        $enable = $this->scopeConfig->getValue(self::MODULE_ENABLED, $storeScope);

        if (!$enable) {
            return $proceed();
        }

        if ($loginPost->getRequest()->isPost()) {
            $login = $loginPost->getRequest()->getPost('login');
            if (!empty($login['username']) && !empty($login['password'])) {

                $customer = $this->getCustomer($login['username']);
                if (!$customer) {
                    return $proceed();
                }

                try {
                    if (!empty($customer->getCustomAttributes())) {
                        if ($this->isAccountNotApproved($customer)) {
                            $this->messageManager->addErrorMessage(__('Your account is not approved.
                            Kindly contact website admin for assistance.'));

                            return $this->resultRedirectFactory->create()
                                    ->setPath('customer/account/login');
                            //@todo:: redirect to last visited url
                        } else {
                            return $proceed();
                        }
                    } else {
                        // if no custom attributes found
                        return $proceed();
                    }
                } catch (\Exception $e) {
                    $message = "Invalid User credentials.";
                    $this->messageManager->addError($message);
                    $this->session->setUsername($login['username']);
                    return $this->resultRedirectFactory->create()
                                    ->setPath('customer/account/login');
                }

            } else {
                // call the original execute function
                return $proceed();
            }
        } else {
            // call the original execute function
            return $proceed();
        }
    }

    /**
     * Get customer by email
     * @param string $email
     * @return \Magento\Customer\Api\Data\CustomerInterface
     */
    public function getCustomer($email)
    {
        try {
            $this->currentCustomer = $this->customerRepositoryInterface->get($email);
            return $this->currentCustomer;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if customer is a vendor and account is approved
     * @param $customer
     * @return bool
     */
    public function isAccountNotApproved($customer)
    {
        $customAttribute = $customer->getCustomAttribute('approve_account');
        if (empty($customAttribute)) {
            return true;
        }
        $isApprovedAccount = $customer->getCustomAttribute('approve_account')->getValue();
        if ($isApprovedAccount) {
            return false;
        }
        return true;
    }
}
