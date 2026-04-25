<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Tenant Isolation Test Suite
 * 
 * Verifies that multi-tenant isolation works correctly:
 * 1. Users can only access their own school's data
 * 2. Global scopes prevent cross-tenant leaks
 * 3. Automatic school_id injection on create
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function users_can_only_access_their_own_school_data()
    {
        // Create two schools (two tenants)
        $schoolA = School::factory()->create(['name' => 'School A']);
        $schoolB = School::factory()->create(['name' => 'School B']);

        // Create users for each school
        $userA = User::factory()->create(['school_id' => $schoolA->id, 'email' => 'a@test.com']);
        $userB = User::factory()->create(['school_id' => $schoolB->id, 'email' => 'b@test.com']);

        // Authenticate as user A
        $this->actingAs($userA);

        // User A should only see School A's data
        $users = User::all();
        $this->assertCount(1, $users); // Only user A
        $this->assertEquals($schoolA->id, $users->first()->school_id);

        // User A should NOT see user B or school B data through queries
        $this->assertDatabaseHas('users', ['email' => 'a@test.com']);
        $this->assertDatabaseMissing('users', ['email' => 'b@test.com']); // Via scoped query check
    }

    /** @test */
    public function automatic_school_id_injection_on_create()
    {
        $school = School::factory()->create();
        $user = User::factory()->create(['school_id' => $school->id]);

        // Simulate authenticated request with tenant context
        $this->actingAs($user);
        app()->instance('tenant', $school);

        // Create a student - school_id should be auto-injected
        $student = new \App\Models\Student([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);
        $student->save();

        // Verify school_id was automatically set
        $this->assertEquals($school->id, $student->school_id);
    }

    /** @test */
    public function global_scope_prevents_cross_tenant_leaks()
    {
        $schoolA = School::factory()->create();
        $schoolB = School::factory()->create();

        $userA = User::factory()->create(['school_id' => $schoolA->id]);
        User::factory()->create(['school_id' => $schoolB->id]);

        // Authenticate as user A
        $this->actingAs($userA);
        app()->instance('tenant', $schoolA);

        // Query should only return user A's school's users
        $users = User::all();
        $this->assertCount(1, $users);
        $this->assertEquals($schoolA->id, $users->first()->school_id);

        // Bypass scope explicitly - should see all (only for system-level operations)
        $allUsers = User::withoutGlobalScopes()->get();
        $this->assertCount(2, $allUsers);
    }

    /** @test */
    public function tenant_middleware_binds_correct_context()
    {
        $school = School::factory()->create();
        $user = User::factory()->create(['school_id' => $school->id]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);

        // Tenant should be available in request
        $this->assertEquals($school->id, app('tenant')->id);
    }

    /** @test */
    public function missing_tenant_context_logs_out_user()
    {
        // Create user with null school_id (invalid state)
        $user = User::factory()->create(['school_id' => null]);

        $response = $this->actingAs($user)->get('/dashboard');

        // Should redirect to login with error
        $response->assertRedirect('/login');
    }
}
