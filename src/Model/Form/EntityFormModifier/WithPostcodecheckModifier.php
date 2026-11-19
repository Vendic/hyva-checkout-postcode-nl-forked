<?php

declare(strict_types=1);

namespace Trinos\PostcodeNL\Model\Form\EntityFormModifier;

use Hyva\Checkout\Magewire\Component\AbstractForm;
use Hyva\Checkout\Model\Form\EntityFieldInterface;
use Hyva\Checkout\Model\Form\EntityFormInterface;
use Hyva\Checkout\Model\Form\EntityFormModifierInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Trinos\PostcodeNL\Model\Config;
use Trinos\PostcodeNL\Model\PostcodeManagement;

class WithPostcodecheckModifier implements EntityFormModifierInterface
{
    public const string KEY_MANUAL_MODE = 'postcodenl_manual_mode';

    /**
     * The form data paths {@see self::commitOnChange()} sends to the server on change: postcode, house number,
     * house number addition and manual mode.
     */
    private const array COMMITTED_PATHS = [
        AddressInterface::KEY_POSTCODE,
        AddressInterface::KEY_STREET . '.1',
        AddressInterface::KEY_STREET . '.2',
        self::KEY_MANUAL_MODE,
    ];

    /** @var array<int, true> Components that dispatched "{type}_address_saved" in this request. */
    private array $dispatched = [];

    public function __construct(
        private readonly PostcodeManagement $postcodeManagement,
        private readonly Config $config,
    ) {
    }

    public function apply(EntityFormInterface $form): EntityFormInterface
    {
        $form->registerModificationListener(
            'explodeStreetRows',
            'form:build',
            [$this, 'explodeStreetRows']
        );

        $form->registerModificationListener(
            'initPostcodeCheckFields',
            'form:build',
            [$this, 'initPostcodeCheckFields']
        );

        $form->registerModificationListener(
            'manualModeUpdated',
            'form:build:magewire',
            [$this, 'validatePostcode']
        );

        $form->registerModificationListener(
            'postcodenlShippingUpdated',
            'form:updated',
            [$this, 'postcodeCheck']
        );

        $form->registerModificationListener(
            'commitOnChange',
            'form:build:magewire',
            [$this, 'commitOnChange']
        );

        $form->registerModificationListener(
            'dispatchAddressSavedOnPostcodeUpdate',
            'form:updated:data:magewire',
            [$this, 'dispatchAddressSavedOnPostcodeUpdate']
        );

        return $form;
    }

    public function initPostcodeCheckFields(EntityFormInterface $form): void
    {
        $country = $form->getField(AddressInterface::KEY_COUNTRY_ID)->getValue();
        $manualMode = $form->getField(self::KEY_MANUAL_MODE);
        $street = $form->getField(AddressInterface::KEY_STREET);
        $houseNumber = $street->getRelatives()[1] ?? null;
        $addition = $street->getRelatives()[2] ?? null;
        $city = $form->getField(AddressInterface::KEY_CITY);

        $houseNumber?->setAttribute('autocomplete', 'address-line2');
        $addition?->setAttribute('autocomplete', 'address-line3');

        if ($country !== 'NL' || !$this->config->isEnabled()) {
            if ($manualMode) {
                $form->removeField($manualMode);
            }
            $street->enable();
            $city->enable();
        } else {
            $this->onManualModeUpdated($form);
        }
    }

    public function validatePostcode(EntityFormInterface $form): ?array
    {
        if (!$this->config->isEnabled()) {
            return null;
        }
        $manualMode = $form->getField(self::KEY_MANUAL_MODE);
        if (!$manualMode || $manualMode->getValue()) {
            return null;
        }
        $postcode = $form->getField(AddressInterface::KEY_POSTCODE);
        $street = $form->getField(AddressInterface::KEY_STREET);
        $housenumber = $street->getRelatives()[1] ?? null;
        $addition = $street->getRelatives()[2] ?? null;

        if (!$postcode?->getValue() || !$housenumber?->getData('value')) {
            return null;
        }

        $response = json_decode($this->postcodeManagement->getPostcodeInformation(
            $postcode->getValue() ?? '',
            $housenumber?->getValue() ?? '',
            $addition?->getValue() ?? '',
        ), true);

        if (isset($response['exception'])) {
            $manualMode->setAttribute('data-msg-magewire', $response['exception']);
            $manualMode->setAttribute('data-magewire-is-valid', '0');
            return $response;
        }

        if (isset($response['houseNumberAdditions']) && count($response['houseNumberAdditions']) > 1) {
            // The option key should be the same as the label.
            $options = array_combine($response['houseNumberAdditions'], $response['houseNumberAdditions']);
            $addition?->setOptions($options);
            // The street rows render with the "street" alias; a select needs its own renderer.
            $addition?->setData(EntityFieldInterface::INPUT_ALIAS, 'select');
        }

        return $response;
    }

    public function onManualModeUpdated(EntityFormInterface $form): void
    {
        $manualMode = $form->getField(self::KEY_MANUAL_MODE);
        $postcode = $form->getField(AddressInterface::KEY_POSTCODE);
        $street = $form->getField(AddressInterface::KEY_STREET);
        $housenumber = $street->getRelatives()[1] ?? null;
        $addition = $street->getRelatives()[2] ?? null;
        $city = $form->getField(AddressInterface::KEY_CITY);

        if ($manualMode && $manualMode->getValue()) {
            $street->enable();
            $city->enable();
            $addition?->unsetData('options');
        } else {
            $street->disable();
            $city->disable();
        }
    }

    /**
     * Send the postcode fields to the server on change, so the postcode check runs.
     * Hyvä Checkout 3 address forms have no autosave, so commit the deferred wire:model updates on change.
     *
     * @param EntityFormInterface $form
     * @return void
     */
    public function commitOnChange(EntityFormInterface $form): void
    {
        $manualMode = $form->getField(self::KEY_MANUAL_MODE);
        $postcode = $form->getField(AddressInterface::KEY_POSTCODE);
        $street = $form->getField(AddressInterface::KEY_STREET);
        $housenumber = $street?->getRelatives()[1] ?? null;
        $addition = $street?->getRelatives()[2] ?? null;

        foreach ([$postcode, $housenumber, $addition, $manualMode] as $field) {
            if ($field) {
                $field->setAttribute('@change.capture', '$wire.$commit()');
            }
        }
    }

    /**
     * Let the shipping and payment methods and the totals follow a postcode change or a postcode lookup.
     *
     * Hyvä Checkout 3 autosaves the inline address form at the end of every request that updates it, but that
     * autosave tells no other component: only an explicit submit dispatches "{type}_address_saved". Shipping rates
     * depend on the postcode, so the method list kept the rates of the previous postcode until it re-rendered for
     * another reason. Hyvä's own WithCountryModifier solves this for the country select the same way; this does it
     * for the fields {@see self::commitOnChange()} commits.
     *
     * Dispatched while the update is handled, because Magewire collects the events of a component before it runs
     * its dehydrate() (which autosaves). The listeners refresh in a request of their own, after this one saved.
     *
     * @param EntityFormInterface $form
     * @param AbstractForm|null $component
     * @param mixed $value
     * @param string|null $path the updated form data path, e.g. "postcode" or "street.1"
     * @return void
     */
    public function dispatchAddressSavedOnPostcodeUpdate(
        EntityFormInterface $form,
        ?AbstractForm $component = null,
        mixed $value = null,
        ?string $path = null
    ): void {
        // In the address modal the form is a draft that isn't autosaved, so there is nothing to refresh for yet.
        if ($component === null || $component->editing || !$component->autosave) {
            return;
        }

        if (!in_array($path, self::COMMITTED_PATHS, true)) {
            return;
        }

        // A commit sends every changed field at once: dispatch once per request.
        if (isset($this->dispatched[spl_object_id($component)])) {
            return;
        }

        $this->dispatched[spl_object_id($component)] = true;
        $component->dispatch(sprintf('%s_address_saved', $form->getNamespace()));
    }

    public function postcodeCheck(EntityFormInterface $form): void
    {
        $country = $form->getField(AddressInterface::KEY_COUNTRY_ID)->getValue();
        if ($country != 'NL' || !$this->config->isEnabled()) {
            return;
        }

        $manualMode = $form->getField(self::KEY_MANUAL_MODE);
        if ($manualMode && $manualMode->getValue()) {
            return;
        }

        $response = $this->validatePostcode($form);

        $postcode = $form->getField(AddressInterface::KEY_POSTCODE);
        $street = $form->getField(AddressInterface::KEY_STREET);
        $city = $form->getField(AddressInterface::KEY_CITY);
        $housenumber = $street->getRelatives()[1] ?? null;
        $addition = $street->getRelatives()[2] ?? null;

        if (!$response || isset($response['exception'])) {
            $street->setValue('');
            $city->setValue('');
            return;
        }

        $postcode->setValue($response['postcode']);
        $street->setValue($response['street']);
        $city->setValue($response['city']);
        $housenumber?->setValue($response['houseNumber']);
        $addition?->setValue($response['houseNumberAddition']);
    }

    public function explodeStreetRows(EntityFormInterface $form): void
    {
        $streetField = $form->getField(AddressInterface::KEY_STREET);
        foreach ($streetField->getRelatives() as $relative) {
            // Change id so the field can coexist with the original street field
            $relative->setData('id', "{$streetField->getName()}.{$relative->getPosition()}");
            $form->addField($relative);
        }
    }
}
