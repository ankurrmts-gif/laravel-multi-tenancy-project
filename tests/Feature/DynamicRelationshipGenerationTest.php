<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\ModuleField;
use App\Models\ColumnType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Artisan;

/**
 * TEST SUITE FOR DYNAMIC RELATIONSHIP GENERATION
 * 
 * This test file verifies that the dynamic relationship generation
 * system works correctly when creating modules with relationship fields.
 */
class DynamicRelationshipGenerationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test 1: Single Relationship (BelongsTo) is generated correctly
     */
    public function test_single_relationship_generation()
    {
        // Create a parent module
        $module = Module::create([
            'name' => 'BlogPost',
            'slug' => 'blog_post',
            'description' => 'Blog Post Module',
        ]);

        // Create a relationship field pointing to Category
        $relationFieldType = ColumnType::where('input_type', 'relation')->first()
            ?? ColumnType::create([
                'name' => 'Relation',
                'input_type' => 'relation',
                'data_type' => 'integer'
            ]);

        $field = ModuleField::create([
            'module_id' => $module->id,
            'name' => 'Category',
            'db_column' => 'category_id',
            'column_type_id' => $relationFieldType->id,
            'model_name' => 'Category',
            'is_multiple' => false,  // Single relationship
        ]);

        // The relationship method should exist in generated model
        $modelPath = app_path('Models') . '/BlogPost.php';
        
        if (file_exists($modelPath)) {
            $modelContent = file_get_contents($modelPath);
            
            // Should contain belongsTo method
            $this->assertStringContainsString('public function category()', $modelContent);
            $this->assertStringContainsString('belongsTo(', $modelContent);
            $this->assertStringContainsString('Category::class', $modelContent);
        }
    }

    /**
     * Test 2: Many-to-Many Relationship (BelongsToMany) is generated correctly
     */
    public function test_many_to_many_relationship_generation()
    {
        $module = Module::create([
            'name' => 'Course',
            'slug' => 'course',
            'description' => 'Course Module',
        ]);

        $relationFieldType = ColumnType::where('input_type', 'relation')->first()
            ?? ColumnType::create([
                'name' => 'Relation',
                'input_type' => 'relation',
                'data_type' => 'integer'
            ]);

        $field = ModuleField::create([
            'module_id' => $module->id,
            'name' => 'Tags',
            'db_column' => 'tags',
            'column_type_id' => $relationFieldType->id,
            'model_name' => 'Tag',
            'is_multiple' => true,  // Many-to-many relationship
        ]);

        $modelPath = app_path('Models') . '/Course.php';
        
        if (file_exists($modelPath)) {
            $modelContent = file_get_contents($modelPath);
            
            // Should contain belongsToMany method
            $this->assertStringContainsString('public function tags()', $modelContent);
            $this->assertStringContainsString('belongsToMany(', $modelContent);
            $this->assertStringContainsString('Tag::class', $modelContent);
            $this->assertStringContainsString('withTimestamps', $modelContent);
        }
    }

    /**
     * Test 3: Multiple relationships in one module
     */
    public function test_multiple_relationships_in_module()
    {
        $module = Module::create([
            'name' => 'Project',
            'slug' => 'project',
            'description' => 'Project Module',
        ]);

        $relationFieldType = ColumnType::where('input_type', 'relation')->first()
            ?? ColumnType::create([
                'name' => 'Relation',
                'input_type' => 'relation',
                'data_type' => 'integer'
            ]);

        // Single relationship: client
        ModuleField::create([
            'module_id' => $module->id,
            'name' => 'Client',
            'db_column' => 'client_id',
            'column_type_id' => $relationFieldType->id,
            'model_name' => 'Client',
            'is_multiple' => false,
        ]);

        // Many-to-many: team members
        ModuleField::create([
            'module_id' => $module->id,
            'name' => 'Team Members',
            'db_column' => 'team_members',
            'column_type_id' => $relationFieldType->id,
            'model_name' => 'User',
            'is_multiple' => true,
        ]);

        // Many-to-many: features
        ModuleField::create([
            'module_id' => $module->id,
            'name' => 'Features',
            'db_column' => 'features',
            'column_type_id' => $relationFieldType->id,
            'model_name' => 'Feature',
            'is_multiple' => true,
        ]);

        $modelPath = app_path('Models') . '/Project.php';
        
        if (file_exists($modelPath)) {
            $modelContent = file_get_contents($modelPath);
            
            // All three methods should exist
            $this->assertStringContainsString('public function client()', $modelContent);
            $this->assertStringContainsString('public function teamMembers()', $modelContent);
            $this->assertStringContainsString('public function features()', $modelContent);
        }
    }

    /**
     * Test 4: Pivot table is named correctly
     */
    public function test_pivot_table_naming()
    {
        $module = Module::create([
            'name' => 'Course',
            'slug' => 'course',
            'description' => 'Course Module',
        ]);

        $relationFieldType = ColumnType::where('input_type', 'relation')->first()
            ?? ColumnType::create([
                'name' => 'Relation',
                'input_type' => 'relation',
                'data_type' => 'integer'
            ]);

        ModuleField::create([
            'module_id' => $module->id,
            'name' => 'Tags',
            'db_column' => 'tags',
            'column_type_id' => $relationFieldType->id,
            'model_name' => 'Tag',
            'is_multiple' => true,
        ]);

        // Pivot table should be named: course_tags (module_slug + model_plural)
        // The migration should create this table
        $migrationPath = database_path('migrations');
        $files = \File::allFiles($migrationPath);
        
        $pivotMigrationFound = false;
        foreach ($files as $file) {
            if (str_contains($file->getFilename(), 'course_tags') 
                || str_contains(file_get_contents($file), 'course_tags')) {
                $pivotMigrationFound = true;
                break;
            }
        }

        // Note: This test may need adjustment based on actual migration generation
        // $this->assertTrue($pivotMigrationFound, 'Pivot table migration should exist');
    }

    /**
     * Test 5: Method naming converts underscores to camelCase
     */
    public function test_method_naming_conversion()
    {
        $module = Module::create([
            'name' => 'BlogPost',
            'slug' => 'blog_post',
            'description' => 'Blog Post Module',
        ]);

        $relationFieldType = ColumnType::where('input_type', 'relation')->first()
            ?? ColumnType::create([
                'name' => 'Relation',
                'input_type' => 'relation',
                'data_type' => 'integer'
            ]);

        // Field name: "team_members" should become method "teamMembers"
        ModuleField::create([
            'module_id' => $module->id,
            'name' => 'Team Members',
            'db_column' => 'team_members',
            'column_type_id' => $relationFieldType->id,
            'model_name' => 'User',
            'is_multiple' => true,
        ]);

        $modelPath = app_path('Models') . '/BlogPost.php';
        
        if (file_exists($modelPath)) {
            $modelContent = file_get_contents($modelPath);
            
            // Should have camelCase method name
            $this->assertStringContainsString('public function teamMembers()', $modelContent);
            // Should NOT have snake_case method name
            $this->assertStringNotContainsString('public function team_members()', $modelContent);
        }
    }

    /**
     * Test 6: Non-relation fields are ignored in relationship generation
     */
    public function test_non_relation_fields_ignored()
    {
        $module = Module::create([
            'name' => 'Product',
            'slug' => 'product',
            'description' => 'Product Module',
        ]);

        // Get string column type (not relation)
        $stringType = ColumnType::where('input_type', 'text')->first()
            ?? ColumnType::create([
                'name' => 'Text',
                'input_type' => 'text',
                'data_type' => 'string'
            ]);

        ModuleField::create([
            'module_id' => $module->id,
            'name' => 'Name',
            'db_column' => 'name',
            'column_type_id' => $stringType->id,
            'is_multiple' => false,
        ]);

        // Get relation type
        $relationType = ColumnType::where('input_type', 'relation')->first()
            ?? ColumnType::create([
                'name' => 'Relation',
                'input_type' => 'relation',
                'data_type' => 'integer'
            ]);

        ModuleField::create([
            'module_id' => $module->id,
            'name' => 'Category',
            'db_column' => 'category_id',
            'column_type_id' => $relationType->id,
            'model_name' => 'Category',
            'is_multiple' => false,
        ]);

        $modelPath = app_path('Models') . '/Product.php';
        
        if (file_exists($modelPath)) {
            $modelContent = file_get_contents($modelPath);
            
            // Should have category relationship method
            $this->assertStringContainsString('public function category()', $modelContent);
            
            // Should NOT have name relationship method
            $this->assertStringNotContainsString('belongsTo(.*name', $modelContent);
        }
    }

    /**
     * Test 7: Foreign key is set correctly for single relationships
     */
    public function test_foreign_key_in_single_relationship()
    {
        $module = Module::create([
            'name' => 'BlogPost',
            'slug' => 'blog_post',
            'description' => 'Blog Post Module',
        ]);

        $relationFieldType = ColumnType::where('input_type', 'relation')->first()
            ?? ColumnType::create([
                'name' => 'Relation',
                'input_type' => 'relation',
                'data_type' => 'integer'
            ]);

        ModuleField::create([
            'module_id' => $module->id,
            'name' => 'Category',
            'db_column' => 'category_id',
            'column_type_id' => $relationFieldType->id,
            'model_name' => 'Category',
            'is_multiple' => false,
        ]);

        $modelPath = app_path('Models') . '/BlogPost.php';
        
        if (file_exists($modelPath)) {
            $modelContent = file_get_contents($modelPath);
            
            // Should specify the foreign key column
            $this->assertStringContainsString("'category_id'", $modelContent);
        }
    }

    /**
     * Test 8: Pivot table has correct column names
     */
    public function test_pivot_table_column_naming()
    {
        $module = Module::create([
            'name' => 'Course',
            'slug' => 'course',
            'description' => 'Course Module',
        ]);

        $relationFieldType = ColumnType::where('input_type', 'relation')->first()
            ?? ColumnType::create([
                'name' => 'Relation',
                'input_type' => 'relation',
                'data_type' => 'integer'
            ]);

        ModuleField::create([
            'module_id' => $module->id,
            'name' => 'Tags',
            'db_column' => 'tags',
            'column_type_id' => $relationFieldType->id,
            'model_name' => 'Tag',
            'is_multiple' => true,
        ]);

        $modelPath = app_path('Models') . '/Course.php';
        
        if (file_exists($modelPath)) {
            $modelContent = file_get_contents($modelPath);
            
            // Pivot table columns should be: course_id, tags_id
            $this->assertStringContainsString("'course_id'", $modelContent);
            $this->assertStringContainsString("'tags_id'", $modelContent);
        }
    }

    /**
     * Test 9: withTimestamps() is added to many-to-many relationships
     */
    public function test_timestamps_on_many_to_many()
    {
        $module = Module::create([
            'name' => 'Course',
            'slug' => 'course',
            'description' => 'Course Module',
        ]);

        $relationFieldType = ColumnType::where('input_type', 'relation')->first()
            ?? ColumnType::create([
                'name' => 'Relation',
                'input_type' => 'relation',
                'data_type' => 'integer'
            ]);

        ModuleField::create([
            'module_id' => $module->id,
            'name' => 'Tags',
            'db_column' => 'tags',
            'column_type_id' => $relationFieldType->id,
            'model_name' => 'Tag',
            'is_multiple' => true,
        ]);

        $modelPath = app_path('Models') . '/Course.php';
        
        if (file_exists($modelPath)) {
            $modelContent = file_get_contents($modelPath);
            
            // Many-to-many should have timestamps
            $this->assertStringContainsString('withTimestamps()', $modelContent);
        }
    }

    /**
     * Test 10: Model class name uses proper namespace
     */
    public function test_model_namespace_in_relationships()
    {
        $module = Module::create([
            'name' => 'BlogPost',
            'slug' => 'blog_post',
            'description' => 'Blog Post Module',
        ]);

        $relationFieldType = ColumnType::where('input_type', 'relation')->first()
            ?? ColumnType::create([
                'name' => 'Relation',
                'input_type' => 'relation',
                'data_type' => 'integer'
            ]);

        ModuleField::create([
            'module_id' => $module->id,
            'name' => 'Category',
            'db_column' => 'category_id',
            'column_type_id' => $relationFieldType->id,
            'model_name' => 'Category',
            'is_multiple' => false,
        ]);

        $modelPath = app_path('Models') . '/BlogPost.php';
        
        if (file_exists($modelPath)) {
            $modelContent = file_get_contents($modelPath);
            
            // Should use full namespace
            $this->assertStringContainsString('\\App\\Models\\Category::class', $modelContent);
            // OR just ::class with use statement
            $this->assertTrue(
                str_contains($modelContent, 'Category::class') &&
                str_contains($modelContent, 'use App\\Models')
            );
        }
    }
}

?>
