<?php

namespace Axl\UIDLogin\Plugin;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\CustomerFactory as CustomerFactory;
use Magento\Customer\Model\Session;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Message\ManagerInterface;
use Axl\UIDLogin\Helper\Config;

class CreatePost
{

    /**
     * @var Validator
     */
    private $formKeyValidator;

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
    protected $messageManager;
    /**
     * @var Session
     */
    protected $session;
    /**
     * @var Config
     */
    protected $helperConfig;

    public function __construct(
        Validator $formKeyValidator = null,
        CustomerFactory $customerFactory,
        RedirectInterface $redirect,
        CustomerRepositoryInterface $customerRepository,
        RedirectFactory $resultRedirectFactory,
        ManagerInterface $messageManager,
        Session $session,
        Config $helperConfig
    ) {
        $this->formKeyValidator = $formKeyValidator ?: ObjectManager::getInstance()->get(Validator::class);
        $this->customerFactory = $customerFactory;
        $this->redirect = $redirect;
        $this->customerRepository = $customerRepository;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->messageManager = $messageManager;
        $this->session = $session;
        $this->helperConfig = $helperConfig;
    }

    public function aroundExecute(\Magento\Customer\Controller\Account\CreatePost $subject, \Closure $proceed)
    {
        if ($subject->getRequest()->isPost() && $this->formKeyValidator->validate($subject->getRequest())) {
            $data = $subject->getRequest()->getPostValue();
            if (isset($data['username'])) {
                if ($this->__isUsernameExists($data['username'])) {
                    $resultRedirect = $this->resultRedirectFactory->create();
                    $this->messageManager->addError(__('The username has been taken.'));
                    $this->session->setCustomerFormData($data);
                    $resultRedirect->setUrl($this->redirect->getRefererUrl());
                    return $resultRedirect;
                }
            }
        }
        $result = $proceed();
        return $result;
    }

    private function __isUsernameExists($username)
    {
        $customerCollection = $this->customerFactory->create()->getCollection()
            ->addAttributeToFilter('username', $username)
            ->getFirstItem();
        if ($customerCollection->getId()) {
            return true;
        }
        return false;
    }
}
