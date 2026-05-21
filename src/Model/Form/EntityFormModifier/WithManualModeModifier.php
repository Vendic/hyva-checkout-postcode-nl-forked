<?php 

declare(strict_types=1);

namespace Trinos\PostcodeNL\Model\Form\EntityFormModifier;

use Hyva\Checkout\Model\Form\EntityFormInterface;
use Hyva\Checkout\Model\Form\EntityFormModifierInterface;
use Trinos\PostcodeNL\Model\Config;
use Trinos\PostcodeNL\Model\Form\EntityFormModifier\WithPostcodecheckModifier;

class WithManualModeModifier implements EntityFormModifierInterface
{
    public function __construct(private readonly Config $config)
    {
    }

    public function apply(EntityFormInterface $form): EntityFormInterface
    {
        $form->registerModificationListener(
            'setManualMode',
            'form:boot',
            [$this, 'setManualMode']
        );
        $form->registerModificationListener(
            'setManualModeAfterCountryChange',
            'form:country_id:updated',
            [$this, 'setManualMode']
        );

        return $form;
    }

    /**
     * Set manual mode to true if the country is not NL, so the postcode check is disabled.
     */
    public function setManualMode(EntityFormInterface $form): void
    {
        $manualMode = $form->getField(WithPostcodecheckModifier::KEY_MANUAL_MODE);
        $countryField = $form->getField('country_id');

        if ($countryField->getValue() !== 'NL' || !$this->config->isEnabled()) {
            $manualMode?->setValue(true);
            return;
        }

        $manualMode?->setValue(false);
    }
}
