<?php

namespace Axl\UIDLogin\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Axl\UIDLogin\Helper\Config;

class CheckoutConfigProvider implements ConfigProviderInterface
{
    /**
     * @var Config
     */
    private $config;

    /**
     * CheckoutConfigProvider constructor.
     * @param Config $config
     */
    public function __construct(
        Config $config
    ) {
        $this->config = $config;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        return ['isUIDEnabled' => $this->config->isEnabled()];
    }
}
