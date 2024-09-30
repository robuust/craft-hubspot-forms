<?php

namespace robuust\hubspotforms;

use Craft;
use HubSpot\Factory;
use robuust\hubspotforms\models\Settings;

/**
 * Hubspot Forms plugin.
 */
class Plugin extends Craft\base\Plugin
{
    /**
     * Initializes the plugin.
     */
    public function init()
    {
        parent::init();

        $this->setComponents([
            'hubspot' => Factory::createWithAccessToken($this->settings->accessToken, Craft::createGuzzleClient()),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }
}
