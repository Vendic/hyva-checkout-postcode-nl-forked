<?php

declare(strict_types=1);

namespace Trinos\PostcodeNL\Setup\Patch\Data;

use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Trinos\PostcodeNL\Model\Config;

/**
 * Encrypt the API secrets that were saved in plain text, before the secret field encrypted them.
 */
class EncryptApiSecret implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EncryptorInterface $encryptor,
        private readonly Config $config
    ) {
    }

    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable('core_config_data');
        $rows = $connection->fetchPairs(
            $connection->select()
                ->from($table, ['config_id', 'value'])
                ->where('path = ?', Config::XML_PATH_API_SECRET)
        );

        foreach ($rows as $configId => $value) {
            $value = trim((string)$value);
            if ($value === '' || $this->config->isEncrypted($value)) {
                continue;
            }

            $connection->update(
                $table,
                ['value' => $this->encryptor->encrypt($value)],
                ['config_id = ?' => (int)$configId]
            );
        }

        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
