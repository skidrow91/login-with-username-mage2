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
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Json\Helper\Data;

class AjaxLogin
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

    /**
     * @var CookieManagerInterface
     */
    private $cookieManager;

    /**
     * @var RawFactory
     */
    private $resultRawFactory;

    /**
     * @var Data
     */
    private $helper;

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
        Config $helperConfig,
        RawFactory $resultRawFactory,
        Data $helper,
        CookieManagerInterface $cookieManager = null
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
        $this->resultRawFactory = $resultRawFactory;
        $this->helper = $helper;
        $this->cookieManager = $cookieManager ?: ObjectManager::getInstance()->get(
            CookieManagerInterface::class
        );
    }

    public function aroundExecute(\Magento\Customer\Controller\Ajax\Login $subject, \Closure $proceed)
    {
        if ($this->helperConfig->isEnabled()) {
            $credentials = null;
            $httpBadRequestCode = 400;

            /** @var \Magento\Framework\Controller\Result\Raw $resultRaw */
            $resultRaw = $this->resultRawFactory->create();
            try {
                $credentials = $this->helper->jsonDecode($subject->getRequest()->getContent());
            } catch (\Exception $e) {
                return $resultRaw->setHttpResponseCode($httpBadRequestCode);
            }
            if (!$credentials || $subject->getRequest()->getMethod() !== 'POST' || !$subject->getRequest()->isXmlHttpRequest()) {
                return $resultRaw->setHttpResponseCode($httpBadRequestCode);
            }

            $response = [
                'errors' => false,
                'message' => __('Login successful.')
            ];

            try {
                $customerEmail = $this->__getEmailByUsername($credentials['uid']);
                if ($customerEmail) {
                    $customer = $this->customerAccountManagement->authenticate(
                        $customerEmail,
                        $credentials['password']
                    );
                    $this->session->setCustomerDataAsLoggedIn($customer);
                    $this->session->regenerateId();
                    $redirectRoute = $this->getAccountRedirect()->getRedirectCookie();
                    if ($this->cookieManager->getCookie('mage-cache-sessid')) {
                        $metadata = $this->cookieMetadataFactory->createCookieMetadata();
                        $metadata->setPath('/');
                        $this->cookieManager->deleteCookie('mage-cache-sessid', $metadata);
                    }
                    if (!$this->getScopeConfig()->getValue('customer/startup/redirect_dashboard') && $redirectRoute) {
                        $response['redirectUrl'] = $this->_redirect->success($redirectRoute);
                        $this->getAccountRedirect()->clearRedirectCookie();
                    }
                }
            } catch (LocalizedException $e) {
                $response = [
                    'errors' => true,
                    'message' => $e->getMessage(),
                    'captcha' => $this->session->getData('user_login_show_captcha')
                ];
            } catch (\Exception $e) {
                $response = [
                    'errors' => true,
                    'message' => __('Invalid login or password.'),
                    'captcha' => $this->session->getData('user_login_show_captcha')
                ];
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
