<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('klaviyo.private_api_key', '');
    }

    public function down()
    {
        $this->migrator->delete('klaviyo.private_api_key');
    }
};
