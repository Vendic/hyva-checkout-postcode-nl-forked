<?php

declare(strict_types=1);

namespace Trinos\PostcodeNL\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    public const string XML_PATH_ENABLED = 'postcodenl_api/general/enabled';
    public const string XML_PATH_API_KEY = 'postcodenl_api/general/api_key';
    public const string XML_PATH_API_SECRET = 'postcodenl_api/general/api_secret';

    /** What a value encrypted by Magento looks like: "<key version>:<cipher version>:<data>". */
    private const string ENCRYPTED_VALUE_PATTERN = '/^\d+:\d+:\S+$/';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    public function isEnabled(?string $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getApiKey(int|string|null $storeId = null): string
    {
        return trim((string)$this->scopeConfig->getValue(self::XML_PATH_API_KEY, ScopeInterface::SCOPE_STORE, $storeId));
    }

    /**
     * The secret is saved encrypted, like Postcode.nl's own module does, so a store that switches from that module
     * keeps its secret. A secret saved in plain text before this module encrypted it is used as it is.
     */
    public function getApiSecret(int|string|null $storeId = null): string
    {
        $secret = trim((string)$this->scopeConfig->getValue(
            self::XML_PATH_API_SECRET,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));

        return $this->isEncrypted($secret) ? (string)$this->encryptor->decrypt($secret) : $secret;
    }

    public function isEncrypted(string $value): bool
    {
        return preg_match(self::ENCRYPTED_VALUE_PATTERN, $value) === 1;
    }
}
