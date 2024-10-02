<?php

namespace robuust\hubspotforms\fields;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\enums\AttributeStatus;
use craft\fields\Dropdown;
use craft\helpers\Json;
use robuust\hubspotforms\Plugin as HubSpotForms;

/**
 * HubSpot Form Field.
 *
 * @author    Bob Olde Hampsink <bob@robuust.digital>
 * @copyright Copyright (c) 2024, Robuust
 * @license   MIT
 *
 * @see       https://robuust.digital
 */
class HubSpotForm extends Dropdown
{
    /**
     * {@inheritdoc}
     */
    public static function displayName(): string
    {
        return Craft::t('app', 'HubSpot Form');
    }

    /**
     * {@inheritdoc}
     */
    public function init(): void
    {
        parent::init();

        // Get all lists
        try {
            // Use apiRequest instead of forms API as workaround: https://github.com/HubSpot/hubspot-api-php/issues/294
            $request = HubSpotForms::$plugin->hubspot->apiRequest(['path' => '/marketing/v3/forms']);
            $response = Json::decode((string) $request->getBody());
            $results = $response['results'];
        } catch (\Exception) {
            $results = [];
        }

        // Set as dropdown options
        foreach ($results as $result) {
            $this->options[] = [
                'value' => $result['id'],
                'label' => $result['name'],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getStatus(ElementInterface $element): ?array
    {
        // If the value is invalid and has a default value (which is going to be pulled in via inputHtml()),
        // preemptively mark the field as modified
        /** @var SingleOptionFieldData $value */
        $value = $element->getFieldValue($this->handle);

        if (!isset($value['id']) || !$value['id'] && $this->defaultValue() !== null) {
            return [
                AttributeStatus::Modified,
                Craft::t('app', 'This field has been modified.'),
            ];
        }

        return Field::getStatus($element);
    }

    /**
     * {@inheritdoc}
     */
    public function normalizeValue(mixed $value, ?ElementInterface $element = null): mixed
    {
        // Get form id
        $form = (string) parent::normalizeValue($this->getFormFieldId($value), $element);

        return Craft::$app->getCache()->getOrSet('hubspotForm:'.$form, function () use ($form) {
            // Use apiRequest instead of forms API as workaround: https://github.com/HubSpot/hubspot-api-php/issues/294
            $request = HubSpotForms::$plugin->hubspot->apiRequest(['path' => '/marketing/v3/forms/'.$form]);

            return Json::decode((string) $request->getBody());
        });
    }

    /**
     * {@inheritdoc}
     */
    public function serializeValue(mixed $value, ?ElementInterface $element = null): mixed
    {
        return parent::serializeValue($this->getFormFieldId($value), $element);
    }

    /**
     * {@inheritdoc}
     */
    public function getElementValidationRules(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    protected function inputHtml(mixed $value, ?ElementInterface $element, bool $inline): string
    {
        /** @var SingleOptionFieldData $value */
        $options = $this->translatedOptions();

        return Craft::$app->getView()->renderTemplate('_includes/forms/select', [
            'id' => $this->getInputId(),
            'describedBy' => $this->describedBy,
            'name' => $this->handle,
            'value' => $this->getFormFieldId($value),
            'options' => $options,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getSettings(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getSettingsHtml(): ?string
    {
        return null;
    }

    /**
     * Get form field id.
     *
     * @param mixed $form
     *
     * @return string
     */
    protected function getFormFieldId(mixed $form): string
    {
        if (is_array($form) && isset($form['id'])) {
            $form = $form['id'];
        }

        return $form;
    }
}
