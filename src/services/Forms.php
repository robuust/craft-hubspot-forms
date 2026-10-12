<?php

namespace robuust\hubspotforms\services;

use Craft;
use craft\helpers\Json;
use HubSpot\Discovery\Discovery;
use Psr\Http\Message\ResponseInterface;
use robuust\hubspotforms\exceptions\SubmissionValidationException;
use robuust\hubspotforms\Plugin;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\validators\Validator;

/**
 * Retrieve HubSpot form definitions and validate submissions before forwarding.
 */
class Forms extends Component
{
    /**
     * Get a definition, optionally using the existing rendering cache.
     *
     * @param string $formId
     * @param bool   $useCache
     *
     * @return array
     */
    public function getForm(string $formId, bool $useCache = false): array
    {
        if ($useCache) {
            return Craft::$app->getCache()->getOrSet('hubspotForm:'.$formId,
                /**
                 * Fetch the definition for rendering.
                 *
                 * @return array
                 */
                fn (): array => $this->getForm($formId)
            );
        }

        /** @var Discovery $hubspot */
        $hubspot = Plugin::$plugin->get('hubspot');
        // Keep the raw API workaround used by the field: hubspot-api-php issue #294.
        $response = $hubspot->apiRequest(['path' => '/marketing/v3/forms/'.rawurlencode($formId)]);
        $form = Json::decode((string) $response->getBody());

        if (($form['id'] ?? null) !== $formId || !isset($form['fieldGroups']) || !is_array($form['fieldGroups']) || ($form['archived'] ?? false)) {
            throw new InvalidConfigException('A current HubSpot form definition is required.');
        }

        return $form;
    }

    /**
     * Validate against a fresh server-side definition and apply missing hidden defaults.
     * Transport parameters such as hs_context are preserved for the caller.
     *
     * @param string $formId
     * @param array  $data   flat v2 submission parameters, without Craft routing/CSRF parameters
     *
     * @return array validated submission parameters, including hidden defaults
     *
     * @throws SubmissionValidationException
     * @throws InvalidConfigException
     */
    public function validateSubmission(string $formId, array $data): array
    {
        $form = $this->getForm($formId);
        $errors = [];

        foreach ($form['fieldGroups'] as $group) {
            if (($group['groupType'] ?? 'default_group') !== 'default_group') {
                throw new InvalidConfigException('Only default HubSpot field groups are supported for server-side submissions.');
            }

            foreach ($group['fields'] ?? [] as $field) {
                if (!empty($field['dependentFields'])) {
                    throw new InvalidConfigException('Dependent HubSpot fields require conditional validation before server-side submissions can be enabled.');
                }

                $name = $field['name'];
                if (!array_key_exists($name, $data) && ($field['hidden'] ?? false)) {
                    $data[$name] = $field['defaultValue'] ?? implode(';', $field['defaultValues'] ?? []);
                }

                $error = $this->validateField($field, array_key_exists($name, $data) ? $data[$name] : '');
                if ($error !== null) {
                    $errors[$name] = $error;
                }
            }
        }

        if ($errors) {
            throw new SubmissionValidationException($errors);
        }

        return $data;
    }

    /**
     * Validate and forward a flat v2 submission to the fixed HubSpot host.
     * Callers must select the portal/form from trusted server-side configuration.
     *
     * @param string $portalId
     * @param string $formId
     * @param array  $data
     *
     * @return ResponseInterface
     *
     * @throws SubmissionValidationException
     */
    public function submit(string $portalId, string $formId, array $data): ResponseInterface
    {
        $data = $this->validateSubmission($formId, $data);

        return Craft::createGuzzleClient()->request('POST',
            'https://forms.hubspot.com/uploads/form/v2/'.rawurlencode($portalId).'/'.rawurlencode($formId),
            ['form_params' => $data]
        );
    }

    /**
     * Validate one field using its declared type and configured constraints.
     *
     * @param array $field
     * @param mixed $value
     *
     * @return ?string
     */
    private function validateField(array $field, mixed $value): ?string
    {
        $type = $field['fieldType'];
        $validation = $field['validation'] ?? [];

        if (!in_array($type, ['single_line_text', 'multi_line_text', 'email', 'phone', 'mobile_phone', 'number', 'datepicker', 'single_checkbox', 'multiple_checkboxes', 'dropdown', 'radio', 'payment_link_radio'], true)) {
            throw new InvalidConfigException('Unsupported HubSpot field type: '.$type);
        }

        if (!is_string($value)) {
            return 'A string value is required.';
        }

        if (trim($value) === '') {
            return ($field['required'] ?? false) ? 'This field is required.' : null;
        }

        // HubSpot also accepts date values as millisecond timestamps at midnight UTC.
        if ($type === 'datepicker' && preg_match('/^-?[0-9]+$/D', $value)) {
            $timestamp = filter_var($value, FILTER_VALIDATE_INT);

            return $timestamp !== false && $timestamp % 86400000 === 0 ? null : 'Enter a date at midnight UTC.';
        }

        $rules = match ($type) {
            'email' => [['email', ['enableIDN' => true]]],
            'number' => [['number', []]],
            'datepicker' => [['date', ['format' => 'php:Y-m-d']]],
            'single_checkbox' => [['in', ['range' => ['true', 'false'], 'strict' => true]]],
            'dropdown', 'radio', 'payment_link_radio' => [['in', ['range' => array_column($field['options'], 'value'), 'strict' => true]]],
            'phone', 'mobile_phone' => [['match', ['pattern' => '/^\+?[0-9\s().\-]+$/D']]],
            default => [],
        };

        foreach ($rules as [$validator, $options]) {
            $error = null;
            if (!Validator::createValidator($validator, new \yii\base\Model(), [], $options)->validate($value, $error)) {
                return $error;
            }
        }

        if ($type === 'multiple_checkboxes') {
            foreach (explode(';', $value) as $choice) {
                if (!in_array($choice, array_column($field['options'], 'value'), true)) {
                    return 'Select valid options.';
                }
            }
        }

        if ($type === 'single_checkbox' && ($field['required'] ?? false) && $value !== 'true') {
            return 'This checkbox must be checked.';
        }

        if (in_array($type, ['phone', 'mobile_phone', 'number'], true)) {
            $digits = strlen(preg_replace('/[^0-9]/', '', $value));
            if ($digits === 0 || (isset($validation['minAllowedDigits']) && $digits < $validation['minAllowedDigits']) || (isset($validation['maxAllowedDigits']) && $digits > $validation['maxAllowedDigits'])) {
                return 'Enter a value with the allowed number of digits.';
            }
        }

        if ($type === 'email') {
            $domain = strtolower(substr(strrchr($value, '@'), 1));
            foreach ($validation['blockedEmailDomains'] ?? [] as $blockedDomain) {
                if ($domain === strtolower($blockedDomain)) {
                    return 'This email domain is not allowed.';
                }
            }
        }

        return null;
    }
}
