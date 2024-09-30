HubSpot Forms plugin for Craft
=================

Plugin that allows you to display HubSpot forms

## Requirements

This plugin requires Craft CMS 5.0.0 or later.

## Installation

To install the plugin, follow these instructions.

1. Open your terminal and go to your Craft project:

        cd /path/to/project

2. Then tell Composer to load the plugin:

        composer require robuust/craft-hubspot-forms

3. In the Control Panel, go to Settings → Plugins and click the “Install” button for HubSpot Forms.

## Config

Create a file called `hubspot-forms.php` in you Craft config folder with the following contents:

```php
<?php

return [
    // General
    'accessToken' => 'YOUR_ACCESS_TOKEN',
];

```

## Usage

Create a new "HubSpot Forms" field and add it to the desired element's field layout.
Now when editing such element you can select a HubSpot form to use.

In your front-end templates you can render this as a form.
