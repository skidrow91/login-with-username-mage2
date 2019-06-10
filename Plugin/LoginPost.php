<?php

namespace Axl\UIDLogin\Plugin;

use Axl\UIDLogin\Helper\Config;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Account\Redirect as AccountRedirect;
use Magento\Customer\Model\CustomerFactory as CustomerFactory;
use Magento\Customer\Model\Session;
use Magento\Customer\Model\Url as CustomerUrl;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Message\ManagerInterface;

class LoginPost
{

    /**
     * @var Validator
     */
    private $formKeyValidator;

    /**
     * @var \Magento\Customer\Api\AccountManagementInterface
     */
    private $customerAccountManagement;

    /**
     * @var CustomerFactory
     */
    private $customerFactory;
    /**
     * @var RedirectInterface
     */
    private $redirect;
    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;
    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var \Magento\Framework\Stdlib\Cookie\PhpCookieManager
     */
    private $cookieMetadataManager;

    /**
     * @var \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory
     */
    private $cookieMetadataFactory;

    /**
     * @var AccountRedirect
     */
    private $accountRedirect;
    /**
     * @var Session
     */
    private $session;

    /**
     * @var Config
     */
    private $helperConfig;

    public function __construct(
        Validator $formKeyValidator = null,
        CustomerFactory $customerFactory,
        AccountManagementInterface $customerAccountManagement,
        RedirectInterface $redirect,
        CustomerRepositoryInterface $customerRepository,
        RedirectFactory $resultRedirectFactory,
        ManagerInterface $messageManager,
        AccountRedirect $accountRedirect,
        CustomerUrl $customerHelperData,
        Session $session,
        Config $helperConfig
    ) {
        $this->formKeyValidator = $formKeyValidator ?: ObjectManager::getInstance()->get(Validator::class);
        $this->customerFactory = $customerFactory;
        $this->customerAccountManagement = $customerAccountManagement;
        $this->redirect = $redirect;
        $this->customerRepository = $customerRepository;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->messageManager = $messageManager;
        $this->accountRedirect = $accountRedirect;
        $this->customerUrl = $customerHelperData;
        $this->session = $session;
        $this->helperConfig = $helperConfig;
    }

    public function aroundExecute(\Magento\Customer\Controller\Account\LoginPost $subject, \Closure $proceed)
    {
        if ($this->helperConfig->isEnabled()) {
            if ($this->session->isLoggedIn() || !$this->formKeyValidator->validate($subject->getRequest())) {
                /** @var \Magento\Framework\Controller\Result\Redirect $resultRedirect */
                $resultRedirect = $this->resultRedirectFactory->create();
                $resultRedirect->setPath('*/*/');
                return $resultRedirect;
            }
            if ($subject->getRequest()->isPost()) {
                $data = $subject->getRequest()->getPostValue('login');
                if (!empty($data['username']) && !empty($data['password'])) {
                    if (($email = $this->__getEmailByUsername($data['username']))) {
                        try {
                            $customer = $this->customerAccountManagement->authenticate($email, $data['password']);
                            $this->session->setCustomerDataAsLoggedIn($customer);
                            $this->session->regenerateId();
                            if ($this->getCookieManager()->getCookie('mage-cache-sessid')) {
                                $metadata = $this->getCookieMetadataFactory()->createCookieMetadata();
                                $metadata->setPath('/');
                                $this->getCookieManager()->deleteCookie('mage-cache-sessid', $metadata);
                            }
                            $redirectUrl = $this->accountRedirect->getRedirectCookie();
                            if (!$this->getScopeConfig()->getValue('customer/startup/redirect_dashboard') && $redirectUrl) {
                                $this->accountRedirect->clearRedirectCookie();
                                $resultRedirect = $this->resultRedirectFactory->create();
                                // URL is checked to be internal in $this->_redirect->success()
                                $resultRedirect->setUrl($this->redirect->success($redirectUrl));
                                return $resultRedirect;
                            }
                        } catch (EmailNotConfirmedException $e) {
                            $value = $this->customerUrl->getEmailConfirmationUrl($email);
                            $message = __(
                                'This account is not confirmed. <a href="%1">Click here</a> to resend confirmation email.',
                                $value
                            );
                        } catch (UserLockedException $e) {
                            $message = __(
                                'You did not sign in correctly or your account is temporarily disabled.'
                            );
                        } catch (AuthenticationException $e) {
                            $message = __('You did not sign in correctly or your account is temporarily disabled.');
                        } catch (LocalizedException $e) {
                            $message = $e->getMessage();
                        } catch (\Exception $e) {
                            // PA DSS violation: throwing or logging an exception here can disclose customer password
                            $this->messageManager->addError(
                                __('An unspecified error occurred. Please contact us for assistance.')
                            );
                        } finally {
                            if (isset($message)) {
                                $this->messageManager->addError($message);
                                $this->session->setUsername($email);
                            }
                        }
                    } else {
                        $this->messageManager->addError(__('Username is not valid.'));    
                    }
                } else {
                    $this->messageManager->addError(__('A login and a password are required.'));
                }
            }
            return $this->accountRedirect->getRedirect();
        } else {
            $result = $proceed();
            return $result;
        }
    }

    private function __getEmailByUsername($username)
    {
        $customerCollection = $this->customerFactory->create()->getCollection()
            ->addAttributeToFilter('username', $username)
            ->getFirstItem();
        if ($customerCollection->getId()) {
            return $customerCollection->getEmail();
        }
        return false;
    }

    /**
     * Get scope config
     *
     * @return ScopeConfigInterface
     * @deprecated 100.0.10
     */
    private function getScopeConfig()
    {
        if (!($this->scopeConfig instanceof \Magento\Framework\App\Config\ScopeConfigInterface)) {
            return \Magento\Framework\App\ObjectManager::getInstance()->get(
                \Magento\Framework\App\Config\ScopeConfigInterface::class
            );
        } else {
            return $this->scopeConfig;
        }
    }

    /**
     * Retrieve cookie manager
     *
     * @deprecated 100.1.0
     * @return \Magento\Framework\Stdlib\Cookie\PhpCookieManager
     */
    private function getCookieManager()
    {
        if (!$this->cookieMetadataManager) {
            $this->cookieMetadataManager = \Magento\Framework\App\ObjectManager::getInstance()->get(
                \Magento\Framework\Stdlib\Cookie\PhpCookieManager::class
            );
        }
        return $this->cookieMetadataManager;
    }

    /**
     * Retrieve cookie metadata factory
     *
     * @deprecated 100.1.0
     * @return \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory
     */
    private function getCookieMetadataFactory()
    {
        if (!$this->cookieMetadataFactory) {
            $this->cookieMetadataFactory = \Magento\Framework\App\ObjectManager::getInstance()->get(
                \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory::class
            );
        }
        return $this->cookieMetadataFactory;
    }
}
