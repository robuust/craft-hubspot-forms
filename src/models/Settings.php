<?php

namespace robuust\hubspotForms\models;

use craft\base\Model;

/**
 * Settings model.
 */
class Settings extends Model
{
    /**
     * @var int
     */
    public $accessToken;

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['accessToken'], 'required'],
        ];
    }
}
