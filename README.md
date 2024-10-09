HubSpot API Forms plugin for Craft
=================

Plugin that allows you to render HubSpot forms from their API

## Requirements

This plugin requires Craft CMS 5.0.0 or later.

## Installation

To install the plugin, follow these instructions.

1. Open your terminal and go to your Craft project:

        cd /path/to/project

2. Then tell Composer to load the plugin:

        composer require robuust/craft-hubspot-forms

3. In the Control Panel, go to Settings → Plugins and click the “Install” button for HubSpot API Forms.

## Config

Create a file called `hubspot-api-forms.php` in you Craft config folder with the following contents:

```php
<?php

return [
    // General
    'accessToken' => 'YOUR_ACCESS_TOKEN',
];

```

## Usage

Create a new "HubSpot Form" field and add it to the desired element's field layout.
Now when editing such element you can select a HubSpot form to use.

In your front-end templates you can render this as a form.

## Example

Here is an example that renders a HubSpot form. You can change and style this example any way you want.

```twig
{% macro fieldType(field) %}
  {% if field.hidden %}
    hidden
  {% elseif field.fieldType == 'single_line_text' %}
    text
  {% elseif field.fieldType == 'phone' %}
    tel
  {% else %}
    {{ field.fieldType }}
  {% endif %}
{% endmacro %}
{% import _self as utils %}

<form
  action="https://forms.hubspot.com/uploads/form/v2/{{ form.portalId }}/{{ form.form.id }}"
  method="post"
  accept-charset="utf-8"
>
  <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 lg:gap-10">
    {% for fieldGroup in form.form.fieldGroups %}
      {% if fieldGroup.richText is defined %}
        <div class="col-span-2">
          {{ fieldGroup.richText }}
        </div>
      {% endif %}
      {% for field in fieldGroup.fields|default([]) %}
        {% if field.hidden %}
          {{ hiddenInput(field.name, craft.app.request.absoluteUrl) }}
        {% else %}
          <div class="col-span-1 flex flex-col gap-2">
            <label
              for="{{ field.name }}"
              class="text-primary-800 font-bold"
            >
              {{ field.label }}{% if field.required %} *{% endif %}
            </label>
            {% if field.fieldType == 'multi_line_text' %}
              <textarea
                id="{{ field.name }}"
                name="{{ field.name }}"
                {% if field.required %}required{% endif %}
                class="rounded-xl border-none h-24 px-6 resize-none focus:outline-none focus:ring-transparent"
              ></textarea>
            {% else %}
              <input
                id="{{ field.name }}"
                type="{{ utils.fieldType(field)|spaceless }}"
                name="{{ field.name }}"
                {% if field.required %}required{% endif %}
                class="rounded-xl border-none h-12 px-6 focus:outline-none focus:ring-transparent"
              >
            {% endif %}
          </div>
        {% endif %}
      {% endfor %}
    {% endfor %}
    <div class="col-span-2">
      <button
        type="submit"
        aria-label="{{ form.form.displayOptions.submitButtonText }}"
        class="button button-secondary"
      >
        {{ form.form.displayOptions.submitButtonText }}
      </button>
    </div>
  </div>
</form>
```
