<?php

namespace logisticdesign\formiemailup;

use Craft;
use craft\base\Plugin;
use logisticdesign\formiemailup\integrations\emailmarketing\Mailup;
use verbb\formie\events\RegisterIntegrationsEvent;
use verbb\formie\services\Integrations;
use yii\base\Event;

/**
 * Mailup for Formie plugin
 *
 * @method static FormieMailup getInstance()
 * @author Logistic Design <dev@logisticdesign.it>
 * @copyright Logistic Design
 * @license MIT
 */
class FormieMailup extends Plugin
{
    public string $schemaVersion = '1.0.0';

    public static function config(): array
    {
        return [
            'components' => [
                // Define component configs here...
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        // Any code that creates an element query or loads Twig should be deferred until
        // after Craft is fully initialized, to avoid conflicts with other plugins/modules
        Craft::$app->onInit(function() {
            $this->attachEventHandlers();
        });
    }

    private function attachEventHandlers(): void
    {
        Event::on(
            Integrations::class,
            Integrations::EVENT_REGISTER_INTEGRATIONS,
            function(RegisterIntegrationsEvent $event) {
                $event->emailMarketing[] = Mailup::class;
            }
        );
    }
}
