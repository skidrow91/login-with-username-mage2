<?php

namespace Axl\UIDLogin\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UpgradeSchemaInterface;

/**
 * @codeCoverageIgnore
 */
class UpgradeSchema implements UpgradeSchemaInterface
{
    /**
     * {@inheritdoc}
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();
        $connection = $setup->getConnection();

        if (version_compare($context->getVersion(), '1.0.0') < 0) {

            $connection->addColumn(
                $setup->getTable('customer_entity'),
                'username',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'length' => 255,
                    'nullable' => true,
                    'default' => null,
                    'comment' => 'Username',
                    'after' => 'email'
                ]
            );
            $connection->addIndex(
                $setup->getTable('customer_entity'),
                $setup->getIdxName('customer_entity', ['username']),
                ['username']
            );
        }

        $setup->endSetup();
    }
}
