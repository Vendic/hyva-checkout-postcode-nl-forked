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
            [$this, 'setManualModeOnBoot']
        );
        $form->registerModificationListener(
            'setManualModeAfterCountryChange',
            'form:country_id:updated',
            [$this, 'setManualMode']
        );

        return $form;
    }

    /**
     * Turn manual mode on if the country is not NL, so the postcode check is disabled. Leave an NL address as it is.
     *
     * Hyvä Checkout 3 dispatches "form:boot" on every request to the form, after filling it with the values the
     * browser holds. Turning manual mode off here would undo the customer's choice on the next request: the first
     * postcode change in manual mode would disable street and city again and run the postcode check.
     *
     * @param EntityFormInterface $form
     * @return void
     */
    public function setManualModeOnBoot(EntityFormInterface $form): void
    {
        if ($form->getField('country_id')?->getValue() !== 'NL' || !$this->config->isEnabled()) {
            $form->getField(WithPostcodecheckModifier::KEY_MANUAL_MODE)?->setValue(true);
        }
    }

    /**
     * Set manual mode to true if the country is not NL, so the postcode check is disabled, and to false for NL.
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
