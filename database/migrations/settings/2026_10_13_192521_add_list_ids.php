<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up()
    {
        $this->migrator->add('klaviyo.newsletter_list_id', '');
        $this->migrator->add('klaviyo.save_for_later_list_id', '');
    }

    public function down()
    {
        $this->migrator->delete('klaviyo.newsletter_list_id');
        $this->migrator->delete('klaviyo.save_for_later_list_id');
    }
};
