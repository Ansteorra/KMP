<?php

declare(strict_types=1);

use Migrations\BaseMigration;
use Cake\ORM\TableRegistry;

class ConvertAppSettingsToSingleRecord extends BaseMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/migrations/4/en/migrations.html#the-change-method
     * @return void
     */
    public function change(): void
    {
        return;
        $this->table("app_settings_log")
            ->addColumn("key", "string", [
                "default" => null,
                "limit" => 255,
                "null" => false,
            ])
            ->addColumn("modified", "datetime", [
                "default" => null,
                "limit" => null,
                "null" => true,
            ])
            ->addColumn("modified_by", "integer", [
                "default" => null,
                "limit" => null,
                "null" => true,
            ])
            ->create();

        $tbl = TableRegistry::getTableLocator()->get('AppSettings');
        $sourceData = $tbl->find('all')->toArray();
        $data = [];
        foreach ($sourceData as $row) {
            $value = $row->value;
            if ($row->type === 'json' && is_string($value)) {
                $value = json_decode($row->value, true);
            } elseif ($row->type === 'yaml' && is_string($value)) {
                $value = yaml_parse($row->value);
            }
            $data[$row->name] = [
                'value' => $value,
                'required' => $row->required,
            ];
        }
        $newMasterAppSetting = $tbl->newEntity([
            'name' => 'master',
            'value' => json_encode($data),
            'type' => 'json',
            'required' => 1,
            'created' => date('Y-m-d H:i:s'),
            'modified' => date('Y-m-d H:i:s'),
            'created_by' => 1,
            'modified_by' => 1,
        ]);
        $tbl->save($newMasterAppSetting);

        //drop some unused columns from app_settings
        $this->table("app_settings")
            ->removeColumn("created")
            ->removeColumn("created_by")
            ->removeColumn("modified")
            ->removeColumn("modified_by")
            ->removeColumn("type")
            ->removeColumn("required")
            ->update();
    }
}