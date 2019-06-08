<?php

namespace Axl\UIDLogin\Observer;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\CustomerFactory;
use Magento\Framework\App\RequestInterface;

class CustomerRegister implements \Magento\Framework\Event\ObserverInterface
{

    /**
     *
     * @var RequestInterface
     */
    private $request;
    /**
     *
     * @var CustomerFactory
     */
    private $customerFactory;
    /**
     *
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    public function __construct(
        RequestInterface $request,
        \Magento\Customer\Model\ResourceModel\CustomerFactory $customerFactory,
        CustomerRepositoryInterface $customerRepository
    ) {
        $this->request = $request;
        $this->customerFactory = $customerFactory;
        $this->customerRepository = $customerRepository;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $customer = $observer->getCustomer();
        $username = $this->request->getPostValue('username');
        if ($username) {
            $customerRepository = $this->customerRepository->getById($customer->getId());
            $customerRepository->setCustomAttribute('username', $username);
            $this->customerRepository->save($customerRepository);
        }
        return $this;
    }
}
