<?php

namespace robuust\hubspotforms\exceptions;

use yii\base\InvalidArgumentException;

/**
 * A submission that does not satisfy its HubSpot form definition.
 */
class SubmissionValidationException extends InvalidArgumentException
{
    /**
     * @var array<string, string> errors keyed by form field name
     */
    public array $errors;

    /**
     * Create a validation failure without exposing submitted values.
     *
     * @param array<string, string> $errors
     */
    public function __construct(array $errors)
    {
        $this->errors = $errors;
        parent::__construct('The HubSpot form submission is invalid.');
    }
}
