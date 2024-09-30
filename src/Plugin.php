<?php

namespace robuust\hubspotforms;

use Craft;
use craft\events\RegisterComponentTypesEvent;
use craft\services\Fields;
use HubSpot\Factory;
use robuust\hubspotforms\fields\HubSpotForms as HubSpotFormsField;
use robuust\hubspotforms\models\Settings;
use yii\base\Event;

/**
 * HubSpot Forms plugin.
 */
class Plugin extends Craft\base\Plugin
{
    /**
     * Initializes the plugin.
     */
    public function init()
    {
        parent::init();

        // Register hubspot client
        if ($this->settings->accessToken) {
            $client = Factory::createWithAccessToken($this->settings->accessToken, Craft::createGuzzleClient());

            $this->setComponents([
                'hubspot' => $client,
                'hubspotForms' => $client->marketing()->forms()->formsApi(),
            ]);
        }

        // Register fieldtype
        Event::on(Fields::class, Fields::EVENT_REGISTER_FIELD_TYPES, function (RegisterComponentTypesEvent $event) {
            $event->types[] = HubSpotFormsField::class;
        });
    }

    /**
     * {@inheritdoc}
     */
    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }
}
