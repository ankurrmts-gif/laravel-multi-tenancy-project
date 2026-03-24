# Dynamic Relationships - Implementation Guide

## Overview

When creating modules, you can now define relationships to other models by passing a `model_name` in your field definition. The system automatically:

1. ✅ Creates the appropriate table structure (foreign keys for single relations, pivot tables for many-to-many)
2. ✅ Generates relationship methods in the model
3. ✅ Sets up cascading delete rules
4. ✅ Adds timestamps for pivot tables

## 📊 Relationship Types

### 1. **Single Relationship (Belongs-To)**

When `is_multiple: false` and a `model_name` is provided:

```json
{
  "db_column": "category_id",
  "label": "Category",
  "column_type_id": 3,
  "model_name": "Category",
  "is_multiple": false
}
```

**Automatically Creates:**
- Foreign key column: `category_id` in the main table
- Relationship method in model: `category()`
- References: `categories.id` with `onDelete('set null')`

**Generated Model Method:**
```php
public function category() {
    return $this->belongsTo(\App\Models\Category::class, 'category_id');
}
```

**Usage:**
```php
$post = BlogPost::find(1);
$category = $post->category;
$post->load('category');
BlogPost::with('category')->get();
```

### 2. **Many-to-Many Relationship (Belongs-To-Many)**

When `is_multiple: true` and a `model_name` is provided:

```json
{
  "db_column": "tags",
  "label": "Tags",
  "column_type_id": 3,
  "model_name": "Tag",
  "is_multiple": true
}
```

**Automatically Creates:**
- Pivot table: `{module_slug}_{related_table}` (e.g., `course_tags`)
- Relationship method in model: `tags()`
- Foreign keys with cascading delete
- Timestamps on pivot table

**Generated Model Method:**
```php
public function tags() {
    return $this->belongsToMany(
        \App\Models\Tag::class,
        'course_tags',
        'course_id',
        'tags_id'
    )->withTimestamps();
}
```

**Usage:**
```php
$course = Course::find(1);

// Get all tags
$tags = $course->tags()->get();

// Attach tags
$course->tags()->attach([1, 2, 3]);
$course->tags()->attach(4);

// Detach tags
$course->tags()->detach([1, 2]);
$course->tags()->detach(3);

// Sync tags (replace all)
$course->tags()->sync([5, 6, 7]);

// Toggle tag
$course->tags()->toggle(8);

// Count tags
$count = $course->tags()->count();

// Eager load
$courses = Course::with('tags')->get();
```

## 🗄️ Database Tables Generated

### Single Relationship Example
Module: `BlogPost`
Field: `category_id` → `Category` (single)

```sql
CREATE TABLE blog_post (
    id BIGINT PRIMARY KEY,
    category_id BIGINT UNSIGNED NULLABLE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);
```

### Many-to-Many Example
Module: `Course`
Field: `tags` → `Tag` (multiple)

```sql
CREATE TABLE course (
    id BIGINT PRIMARY KEY,
    -- other fields
);

CREATE TABLE course_tags (
    id BIGINT PRIMARY KEY,
    course_id BIGINT UNSIGNED,
    tags_id BIGINT UNSIGNED,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES course(id) ON DELETE CASCADE,
    FOREIGN KEY (tags_id) REFERENCES tags(id) ON DELETE CASCADE
);
```

## 🎯 Complete Module Creation Example

### Request
```json
{
  "module": {
    "model_name": "Project",
    "slug": "project",
    "menu_title": "Projects",
    "status": true,
    "user_type": "all",
    "created_by": 1
  },
  "fields": [
    {
      "db_column": "name",
      "label": "Project Name",
      "column_type_id": 1
    },
    {
      "db_column": "description",
      "label": "Description",
      "column_type_id": 2
    },
    {
      "db_column": "client_id",
      "label": "Client",
      "column_type_id": 3,
      "model_name": "Client",
      "is_multiple": false
    },
    {
      "db_column": "team_members",
      "label": "Team Members",
      "column_type_id": 3,
      "model_name": "User",
      "is_multiple": true
    },
    {
      "db_column": "features",
      "label": "Features",
      "column_type_id": 3,
      "model_name": "Feature",
      "is_multiple": true
    }
  ]
}
```

### Generated Model
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model {
    use HasFactory;

    protected $table = 'project';

    protected $fillable = [
        'name',
        'description',
        'client_id'
    ];

    protected $casts = [];

    // AUTO-GENERATED: Belongs-to Client
    public function client() {
        return $this->belongsTo(\App\Models\Client::class, 'client_id');
    }

    // AUTO-GENERATED: Many-to-many Team Members
    public function teamMembers() {
        return $this->belongsToMany(
            \App\Models\User::class,
            'project_users',
            'project_id',
            'users_id'
        )->withTimestamps();
    }

    // AUTO-GENERATED: Many-to-many Features
    public function features() {
        return $this->belongsToMany(
            \App\Models\Feature::class,
            'project_features',
            'project_id',
            'features_id'
        )->withTimestamps();
    }
}
```

### Generated Tables
```
project
├── id (PK)
├── name
├── description
├── client_id (FK → clients)
├── created_at
└── updated_at

project_users (Pivot)
├── id (PK)
├── project_id (FK → project, cascade)
├── users_id (FK → users, cascade)
├── created_at
└── updated_at

project_features (Pivot)
├── id (PK)
├── project_id (FK → project, cascade)
├── features_id (FK → features, cascade)
├── created_at
└── updated_at
```

## 💻 Usage Patterns

### 1. Create with Relationships
```php
$project = Project::create([
    'name' => 'E-Commerce Platform',
    'description' => 'New project',
    'client_id' => 5
]);

// Attach relationships
$project->teamMembers()->attach([1, 2, 3]);
$project->features()->attach([10, 11, 12]);
```

### 2. Sync Relationships
```php
// Replace all relationships
$project->teamMembers()->sync([1, 2, 4, 5]);
$project->features()->sync([10, 12, 14]);
```

### 3. Load with Relationships
```php
// Single record
$project = Project::with('client', 'teamMembers', 'features')->find(1);

// Multiple records
$projects = Project::with([
    'client',
    'teamMembers:id,name,email',
    'features:id,name'
])->get();

// With conditions
$projects = Project::with([
    'teamMembers' => function($q) {
        $q->where('role', 'developer');
    }
])->get();
```

### 4. Manipulate Relationships
```php
$project = Project::find(1);

// Add member
$project->teamMembers()->attach(6);

// Remove member
$project->teamMembers()->detach(2);

// Toggle
if ($project->teamMembers()->find(3)) {
    $project->teamMembers()->detach(3);
} else {
    $project->teamMembers()->attach(3);
}

// Count
$memberCount = $project->teamMembers()->count();
```

### 5. Query Using Relationships
```php
// WHERE projects have a specific team member
$projects = Project::whereHas('teamMembers', function($q) {
    $q->where('id', 5);
})->get();

// WHERE projects have specific feature
$projects = Project::whereHas('features', function($q) {
    $q->where('features.id', 10);
})->get();

// Count related records
$projects = Project::withCount('teamMembers', 'features')->get();
foreach ($projects as $p) {
    echo $p->team_members_count;  // Number of members
    echo $p->features_count;       // Number of features
}
```

## ⚙️ Field Validation

When creating fields with relationships:

```php
$request->validate([
    'fields.*.db_column' => 'required|string',
    'fields.*.label' => 'required|string',
    'fields.*.column_type_id' => 'required|integer',
    'fields.*.model_name' => 'nullable|string',  // Must be valid model name
    'fields.*.is_multiple' => 'boolean',
]);
```

Valid model names:
- Must exist as a model class in `App\Models\`
- Examples: `Category`, `User`, `Client`, `Feature`, `Tag`

## 🔄 Cascade Behavior

### Single Relationship (Belongs-To)
- Foreign key: `ON DELETE SET NULL`
- If related record is deleted, the foreign key is set to null
- Parent can exist without related record

### Many-to-Many
- Pivot table: `ON DELETE CASCADE`
- If related record is deleted, pivot entries are deleted
- If parent is deleted, all pivot entries are deleted

## 📋 Complete Field Definition

```javascript
{
  // Basic field info
  "db_column": "field_name",          // Database column name
  "label": "Field Label",              // Display label
  "column_type_id": 3,                 // 3 = relationship type
  
  // Relationship config
  "model_name": "RelatedModel",        // Name of related model (e.g., "Category")
  "is_multiple": false,                // false = belongsTo, true = belongsToMany
  
  // Optional field settings
  "status": true,                      // Field active/inactive
  "order_number": 1,                   // Display order
  "is_required": false,                // Validation
  "default_value": null,               // Default value
  "help_text": "Helper text"           // Field help text
}
```

## 🚀 API Examples

### Create Module with Relationships
```bash
POST /api/modules
Content-Type: application/json

{
  "module": { /* module data */ },
  "fields": [
    {
      "db_column": "category_id",
      "label": "Category",
      "column_type_id": 3,
      "model_name": "Category",
      "is_multiple": false
    },
    {
      "db_column": "tags",
      "label": "Tags",
      "column_type_id": 3,
      "model_name": "Tag",
      "is_multiple": true
    }
  ]
}
```

### Get Records with Relationships
```bash
GET /api/posts/1

Response includes:
{
  "id": 1,
  "title": "...",
  "category": { /* category data */ },
  "tags": [ /* array of tags */ ]
}
```

## ❌ Common Issues

### 1. Model Not Found
**Error:** Foreign key references non-existent model
**Solution:** Ensure model exists in `App\Models\` and is valid

### 2. Invalid Table Names
**Error:** SQL error about table doesn't exist
**Solution:** Model must follow Laravel conventions (model name = table name plural, snake_case)

### 3. Relationship Method Not Generated
**Error:** Call to undefined method
**Solution:** Check that `model_name` is provided in field definition

### 4. Cascade Delete Issues
**Error:** Foreign key constraint violation on delete
**Solution:** Understand cascade behavior - pivot tables cascading is intentional

## 🔗 See Also
- [RELATIONSHIP_GUIDE.md](RELATIONSHIP_GUIDE.md) - Manual relationship setup
- [DYNAMIC_RELATIONSHIPS_GUIDE.php](DYNAMIC_RELATIONSHIPS_GUIDE.php) - Code examples
- [MODULE_CREATION_EXAMPLES.php](MODULE_CREATION_EXAMPLES.php) - Module creation patterns
