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

## Server-side submission validation

Rendering a form does not protect its submissions. The plugin's field retrieves a
definition for rendering; it does not intercept forms posted directly to HubSpot
or through another module's proxy. Use the plugin's form service at your Craft
submission boundary:

```php
use robuust\hubspotforms\exceptions\SubmissionValidationException;
use robuust\hubspotforms\Plugin as HubSpotForms;
use yii\web\BadRequestHttpException;

// Inside a POST-only, CSRF-protected controller action. Resolve these IDs from
// the server's configured form, not from an arbitrary submitted target URL.
try {
    $response = HubSpotForms::$plugin->getForms()->submit($portalId, $formId, $body);
} catch (SubmissionValidationException $exception) {
    // $exception->errors contains messages keyed by field name.
    throw new BadRequestHttpException($exception->getMessage(), 0, $exception);
}
```

Pass flat v2 form parameters as strings. Remove Craft's `action`, CSRF parameter,
and proxy `target` before calling the service. `hs_context` and other non-field
parameters are preserved; they are not validated by this service. Portal and form
selection, transport/context validation and spam assessment belong to the caller.

`submit()` calls `validateSubmission()` before making its POST to HubSpot's fixed
v2 submission host. Validation always fetches the current `/marketing/v3/forms/{formId}`
definition using the configured access token. It does not trust a client-supplied
definition or the indefinite rendering cache. Definition/API/configuration errors
propagate and must stop forwarding; do not catch them and continue with the original body.

Validation covers:

- Required fields, including whitespace-only values. Optional empty fields are
  accepted; a non-empty optional field still has to satisfy its declared type.
- Syntax of every field whose `fieldType` is `email`, regardless of its name.
  Forms without an email field have no email requirement. Email validation uses
  Yii's email validator with internationalized domain support and the definition's
  explicit `blockedEmailDomains`. DNS, deliverability and HubSpot's managed default
  email blocklist remain HubSpot's responsibility.
- Numeric values, dates in `YYYY-MM-DD` or UTC-midnight millisecond timestamp format,
  telephone values containing digits and
  common formatting (`+`, spaces, parentheses, dots and hyphens), configured
  minimum/maximum digit counts, single checkboxes (`true`/`false`), and declared
  options for dropdowns, radio fields and multiple checkboxes. Required single
  checkboxes must be `true`. Multiple checkbox values use HubSpot's `;` separator.
- String values for text fields, without restrictions on name characters or a
  minimum message length. Nested/array field values are rejected.
- Defaults for hidden fields omitted from the body: `defaultValue`, otherwise
  `defaultValues` joined with `;`. An explicitly submitted value, including an empty
  string, is retained and validated. Visible defaults do not fill in missing answers.

This initial service supports ordinary `default_group` forms. Dependent fields,
progressive groups, file uploads and unknown field types raise `InvalidConfigException`
and are not forwarded. Conditional visibility, progressive profiling, file handling
and consent policy require an integration designed for those form definitions.
The service does not add a public controller or change existing form actions.
The supported types follow the [HubSpot v3 field models](https://github.com/HubSpot/hubspot-api-php/tree/11.3.0/codegen/Marketing/Forms/Model);
both date formats follow [HubSpot's Forms API announcement](https://community.hubspot.com/t/new-standardization-of-date-formats-for-hubspots-form-submissions-api/64062).

### Custom proxy integration

A custom proxy that submits directly to HubSpot must explicitly call this service
to enable validation. Installing the plugin does not intercept those requests.
To integrate it with an existing proxy:

1. Resolve the submitted target to a server-approved portal/form pair (for example,
   an allowlist of exact targets generated from the site's configured forms).
2. After removing routing/CSRF/target parameters, call the plugin before any spam assessment:

   ```php
   try {
       $body = HubSpotForms::$plugin->getForms()->validateSubmission($formId, $body);
   } catch (SubmissionValidationException $exception) {
       throw new BadRequestHttpException($exception->getMessage(), 0, $exception);
   }
   ```

3. Run any existing spam assessment on the returned body, then forward that same
   body with your HTTP client to the approved target. Do not call `submit()`
   as well: the body has already been validated. API/configuration failures must
   stop the request rather than become permission to forward.
4. Preserve your proxy's response handling. The controller's 400 validation
   response can use your form's error UI; field-specific errors are also
   available on the exception if the integration needs them.

## Example

Here is an example that renders a HubSpot form. You can change and style this example any way you want.
It posts directly to HubSpot and therefore bypasses the server-side service above.
To use server-side validation, post to your Craft controller with `csrfInput()` and
select the configured form in that controller.

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
