<?php

namespace Astrogoat\Klaviyo\Settings;

use Helix\Lego\Settings\AppSettings;
use Illuminate\Validation\Rule;

class KlaviyoSettings extends AppSettings
{
    public string $private_api_key;
    public string $company_id;
    public string $form_id;
    public string $newsletter_list_id;
    public string $save_for_later_list_id;

    public function rules(): array
    {
        return [
            'private_api_key' => Rule::requiredIf($this->enabled === true),
            'company_id' => Rule::requiredIf($this->enabled === true),
            'form_id' => Rule::requiredIf($this->enabled === true),
            'newsletter_list_id' => Rule::requiredIf($this->enabled === true),
            'save_for_later_list_id' => Rule::requiredIf($this->enabled === true),
        ];
    }

    public function description(): string
    {
        return 'Interact with Klaviyo.';
    }

    public static function group(): string
    {
        return 'klaviyo';
    }
}
