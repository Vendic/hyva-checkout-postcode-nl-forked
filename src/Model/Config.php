<?php declare(strict_types=1);

namespace Trinos\PostcodeNL\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    public const XML_PATH_ENABLED = 'postcodenl_api/general/enabled';

    public function __construct(
        protected ScopeConfigInterface $scopeConfig,
    ) {
    }

    public function isEnabled(?string $scopeCode = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $scopeCode
        );
    }
}
