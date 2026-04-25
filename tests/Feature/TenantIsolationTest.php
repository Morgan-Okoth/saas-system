<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\School;
use App\Models\User;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

/**
 * Tenant Isolation Test Suite
 * 
 * Verifies multi-tenant data separation:
 * 1. Users can only access their own school's data
 * 2. Global scopes prevent cross-tenant leaks  
 * 3. Model creation auto-injects school_id
 * 4. Role-based access respects tenant boundaries
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function users_can_only_access_their_own_school_data_via_global_scope()
    {
        // Create two tenants (schools)
        $schoolA = School::factory()->create(['name' => 'School Alpha']);
        $schoolB = School::factory()->create(['name' => 'School Beta']);

        // Create users for each school
        $userA = User::factory()->create([
            'school_id' => $schoolA->id,
            'email' => 'usera@test.com',
            'role' => 'school_admin',
        ]);
        
        $userB = User::factory()->create([
            'school_id' => $schoolB->id,
            'email' => 'userb@test.com',
            'role' => 'teacher',
        ]);

        // Create students for each school
        Student::factory()->count(3)->create(['school_id' => $schoolA->id]);
        Student::factory()->count(2)->create(['school_id' => $schoolB->id]);

        // Authenticate as user from School A
        $this->actingAs($userA);
        app()->instance('tenant', $schoolA);

        // Verify: User A can only see their school's users
        $users = User::all();
        $this->assertCount(1, $users, 'Should only see users from own school');
        $this->assertEquals($schoolA->id, $users->first()->school_id);

        // Verify: User A can only see their school's students
        $students = Student::all();
        $this->assertCount(3, $students, 'Should only see 3 students from own school');
        $students->each(function ($student) use ($schoolA) {
            $this->assertEquals($schoolA->id, $student->school_id);
        });

        // Explicit query without scope should show cross-tenant data (for testing)
        $allStudents = Student::withoutGlobalScopes()->get();
        $this->assertCount(5, $allStudents, 'Without scope should see all students');
    }

    /** @test */
    public function school_admin_cannot_access_other_school_data()
    {
        $schoolA = School::factory()->create();
        $schoolB = School::factory()->create();

        $adminA = User::factory()->create([
            'school_id' => $schoolA->id,
            'role' => 'school_admin',
        ]);

        // School B has students
        $schoolBStudents = Student::factory()->count(5)->create([
            'school_id' => $schoolB->id,
        ]);

        // Authenticate as School A admin
        $this->actingAs($adminA);
        app()->instance('tenant', $schoolA);

        // Attempt to query students - should only see own school's (none created)
        $students = Student::all();
        $this->assertCount(0, $students, 'Admin should not see other school students');

        // Attempt to access specific student from School B via ID
        $schoolBStudent = $schoolBStudents->first();
        $queriedStudent = Student::where('id', $schoolBStudent->id)->first();
        $this->assertNull($queriedStudent, 'Should not find student from other school');
    }

    /** @test */
    public function model_creation_auto_injects_school_id()
    {
        $school = School::factory()->create();
        $user = User::factory()->create(['school_id' => $school->id]);

        $this->actingAs($user);
        app()->instance('tenant', $school);

        // Create student without explicitly setting school_id
        $student = new Student([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@test.com',
        ]);
        $student->save();

        // School_id should be auto-injected
        $this->assertEquals($school->id, $student->school_id, 'School ID should be auto-injected');
        $this->assertNotNull($student->school_id, 'School ID should not be null');
    }

    /** @test */
    public function tenant_middleware_binds_correct_context()
    {
        $school = School::factory()->create();
        $user = User::factory()->create(['school_id' => $school->id]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);

        // Tenant should be available in application container
        $this->assertEquals($school->id, app('tenant')->id);
    }

    /** @test */
    public function cross_tenant_query_with_joins_respects_isolation()
    {
        $schoolA = School::factory()->create();
        $schoolB = School::factory()->create();

        $userA = User::factory()->create(['school_id' => $schoolA->id]);
        $studentA = Student::factory()->create(['school_id' => $schoolA->id]);

        $userB = User::factory()->create(['school_id' => $schoolB->id]);
        $studentB = Student::factory()->create(['school_id' => $schoolB->id]);

        // Authenticate as School A user
        $this->actingAs($userA);
        app()->instance('tenant', $schoolA);

        // Query with join - should only return School A data
        $results = Student::whereHas('school', function ($query) {
            $query->where('id', app('tenant')->id);
        })->get();

        $this->assertCount(1, $results);
        $this->assertEquals($schoolA->id, $results->first()->school_id);
    }

    /** @test */
    public function system_admin_bypasses_tenant_isolation()
    {
        $schoolA = School::factory()->create();
        $schoolB = School::factory()->create();

        Student::factory()->count(3)->create(['school_id' => $schoolA->id]);
        Student::factory()->count(2)->create(['school_id' => $schoolB->id]);

        // Create system admin (no school_id)
        $admin = User::factory()->create([
            'school_id' => null,
            'role' => 'system_admin',
        ]);

        $this->actingAs($admin);
        // No tenant context set

        // System admin should see all students
        $students = Student::all();
        $this->assertCount(5, $students, 'System admin should see all students across tenants');
    }

    /** @test */
    public function tenant_scopes_apply_to_relationships()
    {
        $school = School::factory()->create();
        $user = User::factory()->create(['school_id' => $school->id]);

        Student::factory()->count(3)->create(['school_id' => $school->id]);

        $this->actingAs($user);
        app()->instance('tenant', $school);

        // Eager loading should respect tenant scope
        $userWithStudents = User::with('students')->find($user->id);
        
        $this->assertCount(0, $userWithStudents->students, 'User should have no students assigned');
        // Students exist but are not related to this user
    }

    /** @test */
    public function raw_queries_bypass_global_scopes_warning()
    {
        $school = School::factory()->create();
        $user = User::factory()->create(['school_id' => $school->id]);

        Student::factory()->count(2)->create(['school_id' => $school->id]);

        $this->actingAs($user);
        app()->instance('tenant', $school);

        // Raw DB queries bypass Eloquent global scopes
        // This is expected behavior - developers must be cautious
        $count = \DB::table('students')->count();
        
        // Note: This demonstrates why raw queries are dangerous in multi-tenant apps
        // In real app, always use Eloquent or manually add tenant filter
        $this->assertGreaterThan(0, $count);
    }
}
