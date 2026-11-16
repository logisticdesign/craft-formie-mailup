<?php

namespace logisticdesign\formiemailup\integrations\emailmarketing;

use Craft;
use craft\helpers\App;
use GuzzleHttp\Client;
use Throwable;
use verbb\formie\base\EmailMarketing;
use verbb\formie\base\FormInterface;
use verbb\formie\base\Integration;
use verbb\formie\elements\Submission;
use verbb\formie\models\IntegrationConfig;
use verbb\formie\models\IntegrationField;
use verbb\formie\models\IntegrationResult;

class Mailup extends EmailMarketing
{
    // Properties
    // =========================================================================

    public ?string $url = null;

    // Redeclared without #[FormIntegrationSetting]: unlike the other Email Marketing
    // integrations, the list is set once in the integration settings, not per form.
    public ?string $listId = null;

    public bool|string $doubleOptIn = false;

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

        return Craft::$app->getView()->renderTemplate('formie-mailup/integrations/emailmarketing/consentDatabase/_pluginSettings', $variables);
    }

    public function fetchConfig(): IntegrationConfig
    {
        $fields = [
            new IntegrationField([
                'handle' => 'email',
                'name' => Craft::t('formie-mailup', 'Email'),
                'required' => true,
            ]),
        ];

        return new IntegrationConfig([
            'main' => $fields,
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

    public function getFieldMappingValues(Submission $submission, ?array $fieldMapping, mixed $fieldSettings = null)
    {
        return Integration::getFieldMappingValues($submission, $fieldMapping, $fieldSettings ?? $this->getConfigValue('main'));
    }

    // Protected Methods
    // =========================================================================

    protected function executePayload(Submission $submission): IntegrationResult
    {
        $this->beginPayloadDelivery($submission);

        try {
            $formValues = $this->getFieldMappingValues($submission, $this->fieldMapping);

            $email = (string) ($formValues['email'] ?? '');

            $payload = [
                'list' => (int) App::parseEnv($this->listId),
                'email' => $email !== '' ? mb_strtolower($email) : null,
                'source' => 'website',
                'confirm' => App::parseBooleanEnv($this->doubleOptIn) ? 1 : 0,
                'retCode' => 1,
            ];

            $response = $this->deliverPayload($submission, 'frontend/xmlsubscribe.aspx', $payload, contentType: 'query');

            // The payload was cancelled (e.g. opt-in field not checked)
            if ($response === false) {
                return $this->resultForPayload(true);
            }

            // Mailup replies with a plain numeric code
            $subscriptionCode = (int) trim((string) $response);

            if (in_array($subscriptionCode, [1, -1011], true)) {
                Integration::error($this, Craft::t('formie-mailup', 'Mailup error {code}: {message}', [
                    'code' => $subscriptionCode,
                    'message' => $this->status[$subscriptionCode],
                ]), true);

                return $this->resultForPayload(false);
            }
        } catch (Throwable $e) {
            Integration::apiError($this, $e);

            return $this->resultForPayload(false);
        }

        return $this->resultForPayload(true);
    }

    protected function defineClient(): Client
    {
        $baseUri = rtrim((string) App::parseEnv($this->url), '/') . '/';

        return Craft::createGuzzleClient([
            'base_uri' => $baseUri,
        ]);
    }

    protected function defineFormSettingsSchema(FormInterface $form): array
    {
        // Skip the EmailMarketing schema, which adds a per-form list select
        $schema = Integration::defineFormSettingsSchema($form);

        $schema[] = $this->getOptInFieldSchema();

        $schema[] = $this->createFieldMappingField([
            'name' => 'fieldMapping',
            'dataLabel' => 'Mailup',
            'dataKey' => 'main',
        ]);

        return $schema;
    }

    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['url', 'listId'], 'required'];

        $main = $this->getConfigValue('main');

        // Validate when saving form settings
        $rules[] = [
            ['fieldMapping'], 'validateFieldMapping', 'params' => $main, 'when' => function($model) {
                return $model->enabled;
            }, 'on' => [Integration::SCENARIO_FORM],
        ];

        return $rules;
    }
}
