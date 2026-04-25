<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\School;

/**
 * Permission Seeder - Multi-tenant aware
 * 
 * Creates standard permission groups and roles for each school.
 * Run: php artisan db:seed --class=PermissionSeeder
 * 
 * IMPORTANT: This seeds PER SCHOOL, not globally.
 * Each school gets identical permission structure but isolated records.
 */
class PermissionSeeder extends Seeder
{
    /**
     * Permission groups for school management
     * 
     * Grouped by functional area for easy assignment
     */
    protected array $permissionGroups = [
        // Student Management
        'students' => [
            'view students' => 'View student list and profiles',
            'create students' => 'Add new students to school',
            'edit students' => 'Modify student information',
            'delete students' => 'Remove students (soft delete)',
            'export students' => 'Download student data',
        ],

        // Attendance
        'attendance' => [
            'view attendance' => 'View attendance records',
            'mark attendance' => 'Record daily attendance',
            'edit attendance' => 'Modify attendance records',
            'export attendance' => 'Download attendance reports',
        ],

        // Grades & Assessment
        'grades' => [
            'view grades' => 'View student grades and assessments',
            'create grades' => 'Add grade entries',
            'edit grades' => 'Modify grade entries',
            'delete grades' => 'Remove grade entries',
            'export grades' => 'Download grade reports',
        ],

        // Classes & Courses
        'courses' => [
            'view courses' => 'View course catalog',
            'create courses' => 'Add new courses',
            'edit courses' => 'Modify course details',
            'delete courses' => 'Archive courses',
            'assign teachers' => 'Assign teachers to courses',
        ],

        // Billing & Subscription
        'billing' => [
            'view billing' => 'View invoices and payment history',
            'create payments' => 'Record payment transactions',
            'refund payments' => 'Process refunds',
            'update subscription' => 'Change subscription plan',
            'manage payment methods' => 'Add/remove payment methods',
        ],

        // User Management (within school)
        'users' => [
            'view users' => 'View school staff and teachers',
            'create users' => 'Invite new staff members',
            'edit users' => 'Modify user details',
            'delete users' => 'Remove users from school',
            'assign roles' => 'Grant roles and permissions to users',
        ],

        // School Settings
        'settings' => [
            'view settings' => 'View school configuration',
            'edit settings' => 'Update school profile and preferences',
            'manage storage' => 'Configure file storage settings',
        ],

        // Reports & Analytics
        'reports' => [
            'view reports' => 'Access dashboard and analytics',
            'export reports' => 'Download comprehensive reports',
        ],
    ];

    /**
     * Role definitions with assigned permission groups
     */
    protected array $roles = [
        'super_admin' => [
            'description' => 'System-level administrator with all permissions (bypasses tenant isolation)',
            'all_permissions' => true,
        ],
        'school_admin' => [
            'description' => 'Full access to all school features and settings',
            'groups' => ['students', 'attendance', 'grades', 'courses', 'billing', 'users', 'settings', 'reports'],
        ],
        'teacher' => [
            'description' => 'Can manage courses, grades, and attendance for assigned classes',
            'groups' => ['view students', 'attendance', 'grades', 'courses', 'reports'],
        ],
        'accountant' => [
            'description' => 'Handles billing, payments, and financial reports',
            'groups' => ['view students', 'billing', 'reports'],
        ],
        'staff' => [
            'description' => 'Limited access for support and administrative staff',
            'groups' => ['view students', 'attendance', 'view grades'],
        ],
    ];

    /**
     * Run the seeder for all existing schools
     */
    public function run(): void
    {
        // Seed permissions for each school
        School::each(function (School $school) {
            $this->seedForSchool($school);
        });

        // If no schools exist, create permissions that will be used
        // when schools are created (via observer or registration flow)
        if (School::count() === 0) {
            $this->command?->info('No schools found. Permissions will be created during school registration.');
        }
    }

    /**
     * Seed permissions and roles for a specific school
     */
    public function seedForSchool(School $school): void
    {
        // Set the tenant context
        app()->instance('tenant', $school);

        // Track created permissions for role assignment
        $createdPermissions = [];

        // Create permissions for this school
        foreach ($this->permissionGroups as $group => $permissions) {
            foreach ($permissions as $name => $description) {
                $permission = Permission::firstOrCreate(
                    [
                        'school_id' => $school->id,
                        'name' => $name,
                        'guard_name' => 'web',
                    ],
                    [
                        'group' => $group,
                        'description' => $description,
                    ]
                );
                $createdPermissions[$name] = $permission;
            }
        }

        $this->command?->info("Created " . count($createdPermissions) . " permissions for school: {$school->name}");

        // Create roles for this school
        foreach ($this->roles as $roleName => $roleData) {
            if ($roleName === 'super_admin') {
                // Super admin is system-level, not school-specific
                continue;
            }

            $role = Role::firstOrCreate(
                [
                    'school_id' => $school->id,
                    'name' => $roleName,
                    'guard_name' => 'web',
                ],
                [
                    'description' => $roleData['description'],
                ]
            );

            // Assign permissions to role
            if (!empty($roleData['all_permissions'])) {
                $role->syncPermissions($createdPermissions);
            } elseif (!empty($roleData['groups'])) {
                $groupPermissions = $this->getPermissionsByGroups($roleData['groups'], $createdPermissions);
                $role->syncPermissions($groupPermissions);
            }

            $this->command?->info("  Created role: {$roleName} with " . count($role->permissions) . " permissions");
        }

        // Assign system super_admin role to school creator if needed
        // (handled separately in registration flow)
    }

    /**
     * Get permission objects by group names
     */
    protected function getPermissionsByGroups(array $groups, array $permissions): array
    {
        $result = [];

        foreach ($this->permissionGroups as $groupName => $groupPermissions) {
            if (in_array($groupName, $groups)) {
                foreach (array_keys($groupPermissions) as $permName) {
                    if (isset($permissions[$permName])) {
                        $result[$permName] = $permissions[$permName];
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Create default system super_admin role (not school-specific)
     * 
     * This role bypasses tenant isolation for platform-level operations
     */
    public function createSystemSuperAdmin(): void
    {
        // System roles have school_id = 0 or null
        Role::withoutGlobalScopes()->firstOrCreate(
            [
                'school_id' => 0,
                'name' => 'super_admin',
                'guard_name' => 'web',
            ],
            [
                'description' => 'System administrator with full access to all schools',
            ]
        );
    }
}
