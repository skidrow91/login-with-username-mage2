<?php

namespace Axl\UIDLogin\Model;

use Axl\UIDLogin\Api\AccountManagementInterface;
use Magento\Customer\Model\CustomerFactory;
// use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Handle various customer account actions
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class AccountManagement implements AccountManagementInterface
{
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var \Magento\Customer\Model\CustomerFactory
     */
    private $customerFactory;

    /**
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        CustomerFactory $customerFactory
    ) {
        $this->storeManager = $storeManager;
        $this->customerFactory = $customerFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function isUsernameAvailable($username)
    {
        // if ($websiteId === null) {
        //     $websiteId = $this->storeManager->getStore()->getWebsiteId();
        // }
        $customerCollection = $this->customerFactory->create()->getCollection()
            ->addAttributeToFilter('username', $username)
            ->getFirstItem();
        if ($customerCollection->getId()) {
            return false;
        }
        return true;
    }
}
