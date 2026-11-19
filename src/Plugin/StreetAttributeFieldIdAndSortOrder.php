<?php

declare(strict_types=1);

namespace Trinos\PostcodeNL\Plugin;

use Hyva\Checkout\Model\Form\EntityField\EavEntityAddress\StreetAttributeField;
use Trinos\PostcodeNL\Model\Config;

class StreetAttributeFieldIdAndSortOrder
{
    public function __construct(private readonly Config $config)
    {
    }

    public function afterGetId(StreetAttributeField $subject, ?string $result): ?string
    {
        if (!$this->config->isEnabled()) {
            return $result;
        }

        return $subject->getData('id') ?? $result;
    }

    public function afterGetSortOrder(StreetAttributeField $subject, int $result): int
    {
        if (!$this->config->isEnabled()) {
            return $result;
        }

        return $subject->getConfig()->getSortOrder();
    }
}
