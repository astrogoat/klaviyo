<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('klaviyo.enabled', false);
        $this->migrator->add('klaviyo.private_api_key', '');
        $this->migrator->add('klaviyo.company_id', '');
        $this->migrator->add('klaviyo.form_id', '');
    }

    public function down()
    {
        $this->migrator->delete('klaviyo.enabled');
        $this->migrator->delete('klaviyo.private_api_key');
        $this->migrator->delete('klaviyo.company_id');
        $this->migrator->delete('klaviyo.form_id');
    }
};
