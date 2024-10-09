<?php

namespace robuust\hubspotforms;

use Craft;
use craft\base\Plugin as BasePlugin;
use craft\events\RegisterComponentTypesEvent;
use craft\services\Fields;
use HubSpot\Factory;
use robuust\hubspotforms\fields\HubSpotForm as HubSpotFormField;
use robuust\hubspotforms\models\Settings;
use yii\base\Event;

/**
 * HubSpot API Forms plugin.
 */
class Plugin extends BasePlugin
{
    /**
     * @var ?Plugin
     */
    public static ?Plugin $plugin;

    /**
     * Initializes the plugin.
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;

        // Register hubspot client
        if ($this->settings->accessToken) {
            $this->set('hubspot', Factory::createWithAccessToken($this->settings->accessToken, Craft::createGuzzleClient()));
        }

        // Register fieldtype
        Event::on(Fields::class, Fields::EVENT_REGISTER_FIELD_TYPES, function (RegisterComponentTypesEvent $event) {
            $event->types[] = HubSpotFormField::class;
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
