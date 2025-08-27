<?php

namespace robuust\hubspotforms\models;

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
     * @var int
     */
    public $limit = 150;

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['accessToken'], 'required'],
            [['limit'], 'integer', 'min' => 1, 'max' => 1000],
        ];
    }
}
