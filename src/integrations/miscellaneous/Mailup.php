<?php

namespace logisticdesign\formiemailup\integrations\miscellaneous;

use Craft;
use craft\helpers\App;
use GuzzleHttp\Client;
use Throwable;
use verbb\formie\base\EmailMarketing;
use verbb\formie\base\Integration;
use verbb\formie\base\Miscellaneous;
use verbb\formie\elements\Submission;
use verbb\formie\Formie;
use verbb\formie\models\IntegrationField;
use verbb\formie\models\IntegrationFormSettings;

class Mailup extends EmailMarketing
{
    public ?string $subscribeUrl = null;

    public ?string $subscribeListId = null;

    public ?bool $subscribeDoubleOptIn = null;

    public ?array $fieldMapping = null;

    protected array $status = [
        0 => 'Operation completed successfully',
        1 => 'Generic error',
        2 => 'Invalid email address or mobile number',
        3 => 'Recipient already subscribed',
        -1011 => 'IP not registered',
    ];

    public static function displayName(): string
    {
        return Craft::t('formie', 'Mailup');
    }

    public function getIconUrl(): string
    {
        return Craft::$app->getAssetManager()->getPublishedUrl('@logisticdesign/formiemailup/icon.svg', true);
    }

    public function getDescription(): string
    {
        return Craft::t('formie', 'Integration of Mailup.');
    }

    public function getSettingsHtml(): string
    {
        $variables = $this->getSettingsHtmlVariables();

        return Craft::$app->getView()->renderTemplate('formie-mailup/integrations/miscellaneous/consentDatabase/_pluginSettings', $variables);
    }

    public function getFormSettingsHtml($form): string
    {
        $variables = $this->getFormSettingsHtmlVariables($form);

        return Craft::$app->getView()->renderTemplate('formie-mailup/integrations/miscellaneous/consentDatabase/_formSettings', $variables);
    }

    public function fetchFormSettings(): IntegrationFormSettings
    {
        $fields = [
            new IntegrationField([
                'handle' => 'email',
                'name' => Craft::t('formie-mailup', 'Email'),
                'required' => true,
            ]),
        ];

        return new IntegrationFormSettings([
            'main' => $fields,
        ]);
    }

    public function sendPayload(Submission $submission): bool
    {
        // try {
        //     $formValues = $this->getFieldMappingValues($submission, $this->fieldMapping, $this->getFormSettingValue('main'));

        //     $payload = [
        //         'list' => App::parseEnv($this->subscribeListId),
        //         'email' => $formValues['email'] ?? null,
        //         'source' => 'website',
        //         'confirm' => App::parseEnv($this->subscribeDoubleOptIn),
        //     ];

        //     $this->deliverPayload($submission, '/', $payload);

        // } catch (Throwable $e) {
        //     Integration::apiError($this, $e);

        //     return false;
        // }

        return true;
    }

    public function getClient(): Client
    {
        if ($this->_client) {
            return $this->_client;
        }

        return $this->_client = Craft::createGuzzleClient([
            'base_uri' => App::parseEnv($this->subscribeUrl),
        ]);
    }

    public function fetchConnection(): bool
    {
        try {
            $this->request('GET', '/');
        } catch (Throwable $e) {
            Integration::apiError($this, $e);

            return false;
        }

        return true;
    }

    public function defineRules(): array
    {
        $rules = parent::defineRules();

        // $rules[] = [['subscribeUrl', 'subscribeListId'], 'required'];

        $main = $this->getFormSettingValue('main');

        // Validate when saving form settings
        // $rules[] = [
        //     ['fieldMapping'], 'validateFieldMapping', 'params' => $main, 'when' => function($model) {
        //         return $model->enabled;
        //     }, 'on' => [Integration::SCENARIO_FORM],
        // ];

        return $rules;
    }
}
