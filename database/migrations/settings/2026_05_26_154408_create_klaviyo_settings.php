<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('klaviyo.enabled', false);
        $this->migrator->add('klaviyo.company_id', '');
    }

    public function down()
    {
        $this->migrator->delete('klaviyo.enabled');
        $this->migrator->delete('klaviyo.company_id');
    }
};
