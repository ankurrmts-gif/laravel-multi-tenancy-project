<?php

namespace Database\Seeders;

use App\Models\ColumnType;
use Illuminate\Database\Seeder;

class ColumnTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            ['name' => 'Text', 'input_type' => 'text', 'db_type' => 'string', 'has_options' => false, 'is_active' => true],
            ['name' => 'Textarea', 'input_type' => 'textarea', 'db_type' => 'text', 'has_options' => false, 'is_active' => true],
            ['name' => 'Number', 'input_type' => 'number', 'db_type' => 'integer', 'has_options' => false, 'is_active' => true],
            ['name' => 'Email', 'input_type' => 'email', 'db_type' => 'string', 'has_options' => false, 'is_active' => true],
            ['name' => 'Select', 'input_type' => 'select', 'db_type' => 'string', 'has_options' => true, 'is_active' => true],
            ['name' => 'Radio', 'input_type' => 'radio', 'db_type' => 'string', 'has_options' => true, 'is_active' => true],
            ['name' => 'Checkbox', 'input_type' => 'checkbox', 'db_type' => 'boolean', 'has_options' => true, 'is_active' => true],
            ['name' => 'Date', 'input_type' => 'date', 'db_type' => 'date', 'has_options' => false, 'is_active' => true],
            ['name' => 'File', 'input_type' => 'file', 'db_type' => 'string', 'has_options' => false, 'is_active' => true],
        ];

        foreach ($types as $type) {
            ColumnType::create($type);
        }
    }
}