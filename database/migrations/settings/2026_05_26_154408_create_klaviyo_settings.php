<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('klaviyo.enabled', false);
    }

    public function down()
    {
        $this->migrator->delete('klaviyo.enabled');
    }
};
