<?php

namespace Axl\UIDLogin\Api;

use Magento\Framework\Exception\InputException;

/**
 * Interface for managing customers accounts.
 * @api
 * @since 100.0.2
 */
interface AccountManagementInterface
{
    /**
     * Check if given username is associated with a customer account in given website.
     *
     * @param string $username
     * @param int $websiteId If not set, will use the current websiteId
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function isUsernameAvailable($username);
}
