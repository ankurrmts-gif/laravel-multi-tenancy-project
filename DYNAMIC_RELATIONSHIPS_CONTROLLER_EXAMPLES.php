<?php

/**
 * PRACTICAL EXAMPLES - HOW TO USE DYNAMIC RELATIONSHIPS IN CONTROLLERS
 * 
 * This file demonstrates how to work with dynamically generated relationships
 * in your controller classes.
 */

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Course;
use App\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

// ============================================================================
// EXAMPLE 1: CONTROLLER WITH SINGLE RELATIONSHIP (BELONGS-TO)
// ============================================================================

class BlogPostController extends Controller
{
    /**
     * Store a new blog post with category
     * 
     * When the "BlogPost" module was created with:
     * - category_id field referencing "Category" model (is_multiple: false)
     * 
     * The model automatically has: public function category()
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'category_id' => 'required|exists:categories,id',
        ]);

        $post = BlogPost::create($validated);

        return response()->json([
            'success' => true,
            'post' => $post->load('category')  // Load relationship
        ]);
    }

    /**
     * Get blog post with category
     */
    public function show($id)
    {
        $post = BlogPost::with('category')->findOrFail($id);
        
        return response()->json([
            'post' => $post,
            'category' => $post->category,
            'category_name' => $post->category?->name
        ]);
    }

    /**
     * Update blog post category
     */
    public function updateCategory(Request $request, $id)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id'
        ]);

        $post = BlogPost::find($id);
        $post->update(['category_id' => $validated['category_id']]);

        return response()->json([
            'success' => true,
            'post' => $post->load('category')
        ]);
    }

    /**
     * Get all posts by category
     */
    public function byCategory($categoryId)
    {
        $posts = BlogPost::with('category')
            ->where('category_id', $categoryId)
            ->get();

        return response()->json([
            'posts' => $posts,
            'count' => $posts->count()
        ]);
    }

    /**
     * Get posts and filter by relationship
     */
    public function filterByCategory(Request $request)
    {
        $query = BlogPost::with('category');

        if ($request->has('category_id')) {
            // Method 1: Direct where clause
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('category_name')) {
            // Method 2: Using relationship constraint (whereHas)
            $query->whereHas('category', function($q) use ($request) {
                $q->where('name', $request->category_name);
            });
        }

        return response()->json([
            'posts' => $query->get()
        ]);
    }
}

// ============================================================================
// EXAMPLE 2: CONTROLLER WITH MANY-TO-MANY RELATIONSHIP
// ============================================================================

class CourseController extends Controller
{
    /**
     * Create course with tags
     * 
     * When the "Course" module was created with:
     * - tags field referencing "Tag" model (is_multiple: true)
     * 
     * The model automatically has: public function tags() -> belongsToMany
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'tags' => 'required|array',
            'tags.*' => 'exists:tags,id'
        ]);

        $course = Course::create([
            'name' => $validated['name'],
            'description' => $validated['description']
        ]);

        // Attach tags to course
        $course->tags()->attach($validated['tags']);

        return response()->json([
            'success' => true,
            'course' => $course->load('tags')
        ]);
    }

    /**
     * Get course with all tags
     */
    public function show($id)
    {
        $course = Course::with('tags')->findOrFail($id);

        return response()->json([
            'course' => $course,
            'tags' => $course->tags()->get(),
            'tag_ids' => $course->tags()->pluck('id'),
            'tag_count' => $course->tags()->count()
        ]);
    }

    /**
     * Update course tags (replace all)
     */
    public function updateTags(Request $request, $id)
    {
        $validated = $request->validate([
            'tags' => 'required|array',
            'tags.*' => 'exists:tags,id'
        ]);

        $course = Course::find($id);
        
        // Sync: remove old, add new
        $course->tags()->sync($validated['tags']);

        return response()->json([
            'success' => true,
            'course' => $course->load('tags')
        ]);
    }

    /**
     * Add single tag to course
     */
    public function addTag(Request $request, $id)
    {
        $validated = $request->validate([
            'tag_id' => 'required|exists:tags,id'
        ]);

        $course = Course::find($id);

        // Attach: add without removing others
        if (!$course->tags()->where('tag_id', $validated['tag_id'])->exists()) {
            $course->tags()->attach($validated['tag_id']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Tag added',
            'course' => $course->load('tags')
        ]);
    }

    /**
     * Remove tag from course
     */
    public function removeTag($id, $tagId)
    {
        $course = Course::find($id);
        $course->tags()->detach($tagId);

        return response()->json([
            'success' => true,
            'message' => 'Tag removed'
        ]);
    }

    /**
     * Get courses by tag
     */
    public function byTag($tagId)
    {
        $courses = Course::whereHas('tags', function($query) use ($tagId) {
            $query->where('tag_id', $tagId);
        })
        ->with('tags')
        ->get();

        return response()->json([
            'courses' => $courses,
            'count' => $courses->count()
        ]);
    }

    /**
     * Get courses with multiple tags
     */
    public function byTags(Request $request)
    {
        $tagsIds = $request->input('tag_ids', []);

        $courses = Course::with('tags')
            ->withCount('tags')
            ->get()
            ->filter(function($course) use ($tagsIds) {
                $courseTags = $course->tags()->pluck('id')->toArray();
                $matchingTags = array_intersect($courseTags, $tagsIds);
                return count($matchingTags) == count($tagsIds);
            });

        return response()->json([
            'courses' => $courses->values()
        ]);
    }

    /**
     * Get tag statistics
     */
    public function tagStats()
    {
        $courses = Course::with('tags')
            ->withCount('tags')
            ->orderByDesc('tags_count')
            ->get();

        return response()->json([
            'courses' => $courses->map(function($course) {
                return [
                    'id' => $course->id,
                    'name' => $course->name,
                    'tag_count' => $course->tags_count,
                    'tags' => $course->tags
                ];
            })
        ]);
    }
}

// ============================================================================
// EXAMPLE 3: CONTROLLER WITH MULTIPLE RELATIONSHIPS
// ============================================================================

class ProjectController extends Controller
{
    /**
     * Module created with:
     * - client_id (single, belongsTo)
     * - team_members (multiple, belongsToMany)
     * - features (multiple, belongsToMany)
     */

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'description' => 'required|string',
            'client_id' => 'required|exists:clients,id',
            'team_members' => 'required|array',
            'team_members.*' => 'exists:users,id',
            'features' => 'nullable|array',
            'features.*' => 'exists:features,id'
        ]);

        // Create project
        $project = Project::create([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'client_id' => $validated['client_id']
        ]);

        // Attach many-to-many relationships
        $project->teamMembers()->attach($validated['team_members']);
        
        if (!empty($validated['features'])) {
            $project->features()->attach($validated['features']);
        }

        return response()->json([
            'success' => true,
            'project' => $project->load('client', 'teamMembers', 'features')
        ]);
    }

    public function show($id)
    {
        $project = Project::with('client', 'teamMembers', 'features')->findOrFail($id);

        return response()->json([
            'project' => $project,
            'client_name' => $project->client?->name,
            'team_member_count' => $project->teamMembers()->count(),
            'feature_count' => $project->features()->count()
        ]);
    }

    public function update(Request $request, $id)
    {
        $project = Project::find($id);

        // Update basic fields
        if ($request->has(['name', 'description', 'client_id'])) {
            $project->update($request->only(['name', 'description', 'client_id']));
        }

        // Update team members
        if ($request->has('team_members')) {
            $project->teamMembers()->sync($request->input('team_members'));
        }

        // Update features
        if ($request->has('features')) {
            $project->features()->sync($request->input('features'));
        }

        return response()->json([
            'success' => true,
            'project' => $project->load('client', 'teamMembers', 'features')
        ]);
    }

    public function addTeamMember($projectId, $userId)
    {
        $project = Project::find($projectId);
        $project->teamMembers()->attach($userId);

        return response()->json([
            'success' => true,
            'message' => 'Team member added',
            'team_members' => $project->teamMembers
        ]);
    }

    public function removeTeamMember($projectId, $userId)
    {
        $project = Project::find($projectId);
        $project->teamMembers()->detach($userId);

        return response()->json([
            'success' => true,
            'message' => 'Team member removed'
        ]);
    }

    public function getTeamMembers($id)
    {
        $project = Project::find($id);
        
        return response()->json([
            'team_members' => $project->teamMembers()->get(),
            'count' => $project->teamMembers()->count()
        ]);
    }

    public function getFeatures($id)
    {
        $project = Project::find($id);
        
        return response()->json([
            'features' => $project->features()->get(),
            'count' => $project->features()->count()
        ]);
    }

    /**
     * Complex query: Get projects for specific client with specific team member
     */
    public function getProjectsByClientAndMember($clientId, $userId)
    {
        $projects = Project::where('client_id', $clientId)
            ->whereHas('teamMembers', function($q) use ($userId) {
                $q->where('users.id', $userId);
            })
            ->with('client', 'teamMembers', 'features')
            ->get();

        return response()->json([
            'projects' => $projects
        ]);
    }

    /**
     * Get projects with team size
     */
    public function projectsWithStats()
    {
        $projects = Project::with('client')
            ->withCount(['teamMembers', 'features'])
            ->get()
            ->map(function($project) {
                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'client' => $project->client,
                    'team_size' => $project->team_members_count,
                    'feature_count' => $project->features_count
                ];
            });

        return response()->json([
            'projects' => $projects
        ]);
    }
}

// ============================================================================
// EXAMPLE 4: VALIDATION HELPERS
// ============================================================================

class RelationshipValidator
{
    /**
     * Validate that all IDs exist in related table
     */
    public static function validateRelatedIds($modelClass, array $ids)
    {
        $existing = $modelClass::whereIn('id', $ids)->pluck('id')->toArray();
        $missing = array_diff($ids, $existing);
        
        return [
            'valid' => empty($missing),
            'missing_ids' => $missing,
            'message' => empty($missing) ? null : "Invalid IDs: " . implode(',', $missing)
        ];
    }

    /**
     * Validate attachment before adding relationship
     */
    public static function canAttachRelationship($parentModel, $relationName, $relatedId)
    {
        // Check if already attached
        if ($parentModel->{$relationName}()->where('id', $relatedId)->exists()) {
            return ['valid' => false, 'message' => 'Already attached'];
        }

        // Check if related exists
        $relationQuery = $parentModel->{$relationName}();
        if (!$relationQuery->where('id', $relatedId)->exists()) {
            return ['valid' => false, 'message' => 'Related record not found'];
        }

        return ['valid' => true];
    }
}

// ============================================================================
// EXAMPLE 5: QUERY OPTIMIZATION
// ============================================================================

class OptimizedQueries
{
    /**
     * Get only needed columns from relationships
     */
    public static function efficientLoading()
    {
        // Load with specific columns only
        $projects = Project::with([
            'client:id,name,email',
            'teamMembers:id,name,role',
            'features:id,name'
        ])->get();

        return $projects;
    }

    /**
     * Load with conditions
     */
    public static function conditionalLoading()
    {
        $projects = Project::with([
            'teamMembers' => function($query) {
                $query->where('role', 'developer')
                    ->orderBy('name');
            },
            'features' => function($query) {
                $query->where('status', 'active');
            }
        ])->get();

        return $projects;
    }

    /**
     * Load with counts
     */
    public static function withCounts()
    {
        $projects = Project::withCount([
            'teamMembers',
            'features',
            'teamMembers as developer_count' => function($q) {
                $q->where('role', 'developer');
            }
        ])->get();

        return $projects;
    }

    /**
     * Lazy loading - avoid N+1 queries
     */
    public static function avoidN1Problem()
    {
        // BAD: Will run 1 + N queries
        // $projects = Project::all();
        // foreach ($projects as $project) {
        //     echo $project->teamMembers()->count();  // N additional queries
        // }

        // GOOD: Will run just 2 queries
        $projects = Project::withCount('teamMembers')->get();
        foreach ($projects as $project) {
            echo $project->team_members_count;  // Already loaded
        }

        return $projects;
    }
}

?>
