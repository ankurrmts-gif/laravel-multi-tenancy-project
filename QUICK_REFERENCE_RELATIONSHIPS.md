# Quick Reference Guide - Dynamic Relationships

## Overview
Your Laravel module system now automatically generates relationship methods when you create modules with relationship fields.

---

## Step 1: Create Module with Relationship Fields

When creating a module via the API:

```json
{
  "name": "Project",
  "slug": "project",
  "fields": [
    {
      "name": "Client",
      "db_column": "client_id",
      "column_type": "relation",
      "model_name": "Client",
      "is_multiple": false
    },
    {
      "name": "Team Members",
      "db_column": "team_members",
      "column_type": "relation",
      "model_name": "User",
      "is_multiple": true
    }
  ]
}
```

---

## Step 2: System Auto-Generates

The system automatically:

1. **Creates Migration** - Adds `client_id` column to `projects` table
2. **Creates Model** - Generates `Project` model with relationship methods
3. **Creates Pivot Table** (if many-to-many) - `project_users` table
4. **Generates Methods** - `client()` and `teamMembers()` methods in model

---

## Step 3: Use in Your Code

### Single Relationship (is_multiple: false)

```php
// In your model: public function client()
$project = Project::with('client')->find(1);
echo $project->client->name;

// Create with relationship
$project = Project::create([
    'name' => 'Website Redesign',
    'client_id' => 5  // Foreign key
]);

// Update relationship
$project->update(['client_id' => 10]);
```

### Many-to-Many (is_multiple: true)

```php
// In your model: public function teamMembers()
$project = Project::with('teamMembers')->find(1);
$project->teamMembers->each(fn($user) => echo $user->name);

// Create with relationships
$project = Project::create(['name' => 'New Project']);
$project->teamMembers()->attach([1, 2, 3]);

// Add single member
$project->teamMembers()->attach($userId);

// Remove member
$project->teamMembers()->detach($userId);

// Replace all members
$project->teamMembers()->sync([1, 2, 3]);
```

---

## Configuration Reference

### Field Configuration

| Property | Type | Description | Example |
|----------|------|-------------|---------|
| `name` | string | Display name | "Category" |
| `db_column` | string | Database column name | "category_id" |
| `column_type` | string | Must be "relation" | "relation" |
| `model_name` | string | Related model class name | "Category" |
| `is_multiple` | boolean | false=belongsTo, true=belongsToMany | true/false |

---

## Naming Conventions

| Field | Method Name | Result |
|-------|------------|--------|
| `category_id` | `category` | Singular, removes `_id` |
| `team_members` | `teamMembers` | CamelCase conversion |
| `user_id` | `user` | Simple conversion |
| `featured_products` | `featuredProducts` | Complex conversion |

---

## Generated Database Structure

### Single Relationship
```
projects table
├── id
├── name
└── client_id  ← Foreign key to clients
```

### Many-to-Many Relationship
```
projects table              users table
├── id           ←──┐       ├── id
├── name         │  └───→   ├── name
└── ...          │          └── ...
                 │
             project_users table (Pivot)
             ├── project_id
             ├── users_id
             ├── created_at
             └── updated_at
```

---

## Common Operations

### Load with Relationships
```php
// Load single
$project = Project::with('client')->find(1);

// Load multiple
$project = Project::with(['client', 'teamMembers', 'features'])->find(1);

// Load with conditions
$project = Project::with([
    'teamMembers' => fn($q) => $q->where('active', true)
])->find(1);

// Count without loading
$count = $project->teamMembers()->count();
```

### Query by Relationship
```php
// Projects with specific client
Project::where('client_id', $clientId)->get();

// Projects containing specific team member
Project::whereHas('teamMembers', fn($q) => 
    $q->where('id', $userId)
)->get();

// Projects with at least 5 team members
Project::withCount('teamMembers')
    ->having('team_members_count', '>=', 5)
    ->get();
```

### Sync Relationships
```php
// Replace all (remove old, add new)
$project->teamMembers()->sync([1, 2, 3]);
// Result: Only users 1, 2, 3 are attached

// Sync with additional data
$project->teamMembers()->sync([
    1 => ['role' => 'lead'],
    2 => ['role' => 'developer']
]);

// Attach without detaching
$project->teamMembers()->attach(4);

// Detach specific
$project->teamMembers()->detach($userId);

// Detach all
$project->teamMembers()->detach();
```

---

## API Endpoints (Example)

### Create Module with Relationships
```
POST /api/modules
{
  "name": "Project",
  "fields": [...]
}
```

### Get with Relationships
```
GET /api/projects/1
Response: { project, client, teamMembers }
```

### Add Relationship
```
POST /api/projects/1/team-members
{"user_id": 5}
```

### Remove Relationship
```
DELETE /api/projects/1/team-members/5
```

### Sync Relationships
```
POST /api/projects/1/team-members/sync
{"user_ids": [1, 2, 3]}
```

---

## Testing

Run the test suite:
```bash
php artisan test tests/Feature/DynamicRelationshipGenerationTest.php
```

See `DYNAMIC_RELATIONSHIPS_CONTROLLER_EXAMPLES.php` for concrete usage patterns.

---

## Troubleshooting

### Issue: "Call to undefined method"
**Cause**: Model wasn't regenerated after adding field  
**Solution**: Recreate the module or manually add relationship method

### Issue: "Foreign key constraint fails"
**Cause**: Referenced record doesn't exist  
**Solution**: Ensure related record exists before creating relationship

### Issue: Pivot table not created
**Cause**: Migration didn't run  
**Solution**: Run `php artisan migrate`

### Issue: Method names don't match
**Cause**: Naming convention mismatch  
**Solution**: Use camelCase method names: `teamMembers()` not `team_members()`

---

## See Also
- `DYNAMIC_RELATIONSHIPS_GUIDE.php` - 10 detailed examples
- `DYNAMIC_RELATIONSHIPS_CONTROLLER_EXAMPLES.php` - Controller implementations
- `DYNAMIC_FIELD_RELATIONSHIPS.md` - Full documentation
