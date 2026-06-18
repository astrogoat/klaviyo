<?php

namespace Astrogoat\Klaviyo\Settings;

use Helix\Lego\Settings\AppSettings;
use Illuminate\Validation\Rule;

class KlaviyoSettings extends AppSettings
{
    public string $private_api_key;
    public string $company_id;
    public string $form_id;

    public function rules(): array
    {
        return [
            'private_api_key' => Rule::requiredIf($this->enabled === true),
            'company_id' => Rule::requiredIf($this->enabled === true),
            'form_id' => Rule::requiredIf($this->enabled === true),
        ];
    }

    // public static function encrypted(): array
    // {
    //     return [
    //         'private_api_key',
    //     ];
    // }

    public function description(): string
    {
        return 'Interact with Klaviyo.';
    }

    public static function group(): string
    {
        return 'klaviyo';
    }
}
