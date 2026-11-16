# Release Notes for Mailup for Formie

## 1.0.1
- Fix for opt-in field not checked

## 1.0.0
- Initial release

## 2.0.0
> [!IMPORTANT]
> Requires Formie 4. Use version 1.x for Formie 3.

### Changed
- Updated the integration to the Formie 4 custom integration API:
  - Form settings are now defined with `defineFormSettingsSchema()` instead of the `_formSettings` Twig template. The Opt-In Field is provided by Formie.
  - `fetchFormSettings()` → `fetchConfig()` / `IntegrationConfig`
  - `sendPayload()` → `executePayload()`, returning an `IntegrationResult`
  - `getClient()` → `defineClient()`
- The list (`listId`) stays a global integration setting: Formie 4 makes it a per-form setting for Email Marketing integrations, so the per-form list select is not shown.
- The field mapping now uses the inherited `fieldMapping` property, which Formie 4 saves and populates as a form setting.
- Double Opt-In is now sent to Mailup as `confirm=1` / `confirm=0` (previously `1` / empty).
- Mailup error codes are now logged as integration errors instead of exceptions.
