<?php

/**
 * DYNAMIC RELATIONSHIPS - MODULE CREATION WITH MODEL RELATIONSHIPS
 * 
 * This example shows how to create modules with dynamic relationships
 * between different models.
 */

// ============================================================================
// EXAMPLE 1: CREATE MODULE WITH SINGLE RELATIONSHIP (BELONGS-TO)
// ============================================================================

/*
Create a "BlogPost" module with a relationship to "Category"

POST /api/modules

{
  "module": {
    "model_name": "BlogPost",
    "slug": "blog_post",
    "menu_title": "Blog Posts",
    "status": true,
    "user_type": "all",
    "created_by": 1
  },
  "fields": [
    {
      "db_column": "title",
      "label": "Post Title",
      "column_type_id": 1
    },
    {
      "db_column": "content",
      "label": "Content",
      "column_type_id": 2
    },
    {
      "db_column": "category_id",
      "label": "Category",
      "column_type_id": 3,  // relationship type
      "model_name": "Category",
      "is_multiple": false  // Single selection = belongsTo
    }
  ]
}

RESULT - Generated Model:
<?php
namespace App\Models;

class BlogPost extends Model {
    protected $table = 'blog_post';
    protected $fillable = ['title', 'content', 'category_id'];
    
    // Automatically generated relationship method!
    public function category() {
        return $this->belongsTo(\App\Models\Category::class, 'category_id');
    }
}

USAGE IN CODE:
$post = BlogPost::find(1);
$post->category()->get();  // Get the category
$post->load('category');   // Eager load
$posts = BlogPost::with('category')->get();  // Load all posts with categories
*/

// ============================================================================
// EXAMPLE 2: CREATE MODULE WITH MANY-TO-MANY RELATIONSHIP
// ============================================================================

/*
Create a "Course" module with multiple "Tags"

POST /api/modules

{
  "module": {
    "model_name": "Course",
    "slug": "course",
    "menu_title": "Courses",
    "status": true,
    "user_type": "all",
    "created_by": 1
  },
  "fields": [
    {
      "db_column": "name",
      "label": "Course Name",
      "column_type_id": 1
    },
    {
      "db_column": "description",
      "label": "Description",
      "column_type_id": 2
    },
    {
      "db_column": "tags",
      "label": "Course Tags",
      "column_type_id": 3,  // relationship type
      "model_name": "Tag",
      "is_multiple": true  // Multiple selection = belongsToMany
    }
  ]
}

AUTOMATIC RESULT:
1. Creates main table: course
2. Creates pivot table: course_tags
3. Generates model with relationship

Generated Model:
<?php
namespace App\Models;

class Course extends Model {
    protected $table = 'course';
    protected $fillable = ['name', 'description'];
    
    // Automatically generated many-to-many relationship!
    public function tags() {
        return $this->belongsToMany(
            \App\Models\Tag::class,
            'course_tags',
            'course_id',
            'tags_id'
        )->withTimestamps();
    }
}

USAGE IN CODE:
$course = Course::find(1);
$tags = $course->tags()->get();          // Get all tags
$course->tags()->attach(1);              // Attach a tag
$course->tags()->detach(2);              // Detach a tag
$course->tags()->sync([1, 2, 3]);        // Sync tags (replace all)
$courses = Course::with('tags')->get();  // Eager load
*/

// ============================================================================
// EXAMPLE 3: CREATE MODULE WITH MULTIPLE RELATIONSHIPS
// ============================================================================

/*
Create a "Project" module with multiple relationships

POST /api/modules

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
      "db_column": "company_id",
      "label": "Company",
      "column_type_id": 3,
      "model_name": "Company",
      "is_multiple": false  // Single: belongsTo
    },
    {
      "db_column": "team_members",
      "label": "Team Members",
      "column_type_id": 3,
      "model_name": "User",
      "is_multiple": true  // Multiple: belongsToMany
    },
    {
      "db_column": "features",
      "label": "Features",
      "column_type_id": 3,
      "model_name": "Feature",
      "is_multiple": true  // Multiple: belongsToMany
    }
  ]
}

Generated Model would have:
- company() -> belongsTo Company
- teamMembers() -> belongsToMany User via project_users
- features() -> belongsToMany Feature via project_features

USAGE:
$project = Project::with(['company', 'teamMembers', 'features'])->find(1);
*/

// ============================================================================
// EXAMPLE 4: TABLE STRUCTURE CREATED
// ============================================================================

/*
When you create the above "Project" module, these tables are created:

1. MAIN TABLE: project
   - id (primary key)
   - name (text)
   - company_id (foreign key - references companies.id)
   - created_at, updated_at (timestamps)

2. PIVOT TABLE: project_users (for team members many-to-many)
   - id (primary key)
   - project_id (foreign key - references project.id, onDelete cascade)
   - users_id (foreign key - references users.id, onDelete cascade)
   - created_at, updated_at (timestamps)

3. PIVOT TABLE: project_features (for features many-to-many)
   - id (primary key)
   - project_id (foreign key - references project.id, onDelete cascade)
   - features_id (foreign key - references features.id, onDelete cascade)
   - created_at, updated_at (timestamps)
*/

// ============================================================================
// EXAMPLE 5: API USAGE WITH RELATIONSHIPS
// ============================================================================

// POST - Create project with relationships
POST /api/projects
Content-Type: application/json

{
    "name": "E-commerce Platform",
    "company_id": 5,
    "team_members": [1, 2, 3],
    "features": [10, 11, 12, 13]
}

// GET - Retrieve with relationships loaded
GET /api/projects/1

Response:
{
    "id": 1,
    "name": "E-commerce Platform",
    "company_id": 5,
    "company": {
        "id": 5,
        "name": "Tech Corp"
    },
    "team_members": [
        {"id": 1, "name": "John"},
        {"id": 2, "name": "Jane"},
        {"id": 3, "name": "Bob"}
    ],
    "features": [
        {"id": 10, "name": "Cart"},
        {"id": 11, "name": "Checkout"},
        {"id": 12, "name": "Payments"},
        {"id": 13, "name": "Shipping"}
    ]
}

// PATCH - Update relationships
PATCH /api/projects/1
{
    "team_members": [1, 2, 4, 5],  // Replace team
    "features": [10, 12, 14]        // Replace features
}

// DELETE - Remove relationship
DELETE /api/projects/1/team-members/3  // Remove user 3 from team
*/

// ============================================================================
// EXAMPLE 6: CODE USAGE IN CONTROLLERS
// ============================================================================

class ProjectController {
    
    public function store(Request $request) {
        $validated = $request->validate([
            'name' => 'required|string',
            'company_id' => 'required|exists:companies,id',
            'team_members' => 'required|array',
            'team_members.*' => 'exists:users,id',
            'features' => 'required|array',
            'features.*' => 'exists:features,id',
        ]);

        // Create project
        $project = Project::create([
            'name' => $validated['name'],
            'company_id' => $validated['company_id'],
        ]);

        // Attach relationships
        $project->teamMembers()->attach($validated['team_members']);
        $project->features()->attach($validated['features']);

        return response()->json([
            'success' => true,
            'project' => $project->load('company', 'teamMembers', 'features')
        ]);
    }

    public function show($id) {
        $project = Project::with('company', 'teamMembers', 'features')->find($id);
        return response()->json($project);
    }

    public function update(Request $request, $id) {
        $project = Project::find($id);

        // Update basic fields
        $project->update($request->only(['name']));

        // Sync relationships
        if ($request->has('team_members')) {
            $project->teamMembers()->sync($request->input('team_members'));
        }

        if ($request->has('features')) {
            $project->features()->sync($request->input('features'));
        }

        return response()->json(['success' => true]);
    }

    public function addTeamMember($projectId, $userId) {
        $project = Project::find($projectId);
        $project->teamMembers()->attach($userId);
        return response()->json(['success' => true]);
    }

    public function removeTeamMember($projectId, $userId) {
        $project = Project::find($projectId);
        $project->teamMembers()->detach($userId);
        return response()->json(['success' => true]);
    }
}

// ============================================================================
// EXAMPLE 7: QUERYING WITH RELATIONSHIPS
// ============================================================================

// Get projects with company and team
$projects = Project::with(['company', 'teamMembers'])->get();

// Get projects where company is "Tech Corp"
$projects = Project::whereHas('company', function($query) {
    $query->where('name', 'Tech Corp');
})->get();

// Get projects that have specific team member (user id 5)
$projects = Project::whereHas('teamMembers', function($query) {
    $query->where('users.id', 5);
})->get();

// Get projects with feature count
$projects = Project::withCount('features')->get();
foreach ($projects as $project) {
    echo $project->features_count;  // Number of features
}

// Get projects with feature ids
$projectWithFeatureIds = Project::find(1)
    ->features()
    ->pluck('id')
    ->toArray();  // [10, 11, 12, 13]

// ============================================================================
// EXAMPLE 8: INVERSE RELATIONSHIPS (From Related Models) 
// ============================================================================

// These relationships work in reverse too!

$company = Company::find(5);
$projects = $company->projects()->get();  // If Company model has hasMany('Project')

$feature = Feature::find(10);
$projectsWithThisFeature = $feature->projects()->get();  // If Feature model has belongsToMany

// ============================================================================
// EXAMPLE 9: EAGER LOADING STRATEGIES
// ============================================================================

// Load with counts
$projects = Project::withCount('teamMembers', 'features')->get();

// Load only specific fields from related models
$projects = Project::with([
    'company:id,name',
    'teamMembers:id,name,email',
    'features:id,name'
])->get();

// Load with conditions
$projects = Project::with([
    'teamMembers' => function($query) {
        $query->where('role', 'developer')->orderBy('name');
    },
    'features' => function($query) {
        $query->where('status', 'active');
    }
])->get();

// ============================================================================
// EXAMPLE 10: CREATING COMPLEX MODULES
// ============================================================================

/*
Module: Order

Fields with relationships:
- customer_id -> belongsTo Customer
- items -> belongsToMany Product (order items)
- payment_method_id -> belongsTo PaymentMethod
- delivery_address_id -> belongsTo Address
- tags -> belongsToMany Tag (order tags/labels)

Generated Table: order
Generated Pivot Tables:
- order_products
- order_tags

Generated Relationships:
- customer() -> belongsTo
- items() -> belongsToMany
- paymentMethod() -> belongsTo
- deliveryAddress() -> belongsTo
- tags() -> belongsToMany

Usage:
$order = Order::with([
    'customer',
    'items',
    'paymentMethod',
    'deliveryAddress',
    'tags'
])->find(123);
*/

?>
