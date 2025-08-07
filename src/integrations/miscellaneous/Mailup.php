<?php

namespace logisticdesign\formiemailup\integrations\miscellaneous;

use Craft;
use craft\helpers\App;
use craft\helpers\Json;
use Exception;
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
    // Properties
    // =========================================================================

    public ?string $url = null;
    public ?string $listId = null;
    public bool|string $doubleOptIn = false;

    public ?array $fieldMapping = null;

    protected array $status = [
        0 => 'Operation completed successfully',
        1 => 'Generic error',
        2 => 'Invalid email address or mobile number',
        3 => 'Recipient already subscribed',
        -1011 => 'IP not registered',
    ];

    // Methods
    // =========================================================================

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
        try {
            $formValues = $this->getFieldMappingValues($submission, $this->fieldMapping, $this->getFormSettingValue('main'));

            $email = $formValues['email'] ?? null;

            $payload = [
                'list' => (int) App::parseEnv($this->listId),
                'email' => strlen($email) > 0 ? mb_strtolower($email) : null,
                'source' => 'website',
                'confirm' => App::parseEnv($this->doubleOptIn),
                'retCode' => 1,
            ];

            $response = $this->deliverPayloadRequest($submission, 'frontend/xmlsubscribe.aspx', $payload, contentType: 'query');
            $subscriptionCode = Json::decode($response->getBody());

            if (in_array($subscriptionCode, [1, -1011])) {
                throw new Exception($this->status[$subscriptionCode]);
            }

        } catch (Throwable $e) {
            Integration::apiError($this, $e);

            return false;
        }

        return true;
    }

    public function getClient(): Client
    {
        if ($this->_client) {
            return $this->_client;
        }

        $baseUri = rtrim(App::parseEnv($this->url), '/').'/';

        return $this->_client = Craft::createGuzzleClient([
            'base_uri' => $baseUri,
        ]);
    }

    public function fetchConnection(): bool
    {
        try {
            $this->request('GET', 'frontend/xmlsubscribe.aspx');
        } catch (Throwable $e) {
            Integration::apiError($this, $e);

            return false;
        }

        return true;
    }

    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['url', 'listId'], 'required'];

        $main = $this->getFormSettingValue('main');

        // Validate when saving form settings
        $rules[] = [
            ['fieldMapping'], 'validateFieldMapping', 'params' => $main, 'when' => function($model) {
                return $model->enabled;
            }, 'on' => [Integration::SCENARIO_FORM],
        ];

        return $rules;
    }
}
