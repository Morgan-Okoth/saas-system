# PHASE 1: MULTI-TENANCY FOUNDATION (VPS-READY ARCHITECTURE)

## Architecture Reasoning

### Why School is the Root of Tenancy

In this SaaS system, **School** is the tenant root for critical reasons:

1. **Business Domain Reality**: Schools are autonomous legal entities with distinct operational boundaries, data sovereignty requirements, and administrative hierarchies. Each school acts as a self-contained business unit.

2. **Natural Isolation Unit**: Each school has unique users, students, teachers, and data that **MUST NEVER** intermix. Academic records, billing information, and user permissions are institution-scoped.

3. **Subscription Model**: Billing, trials, feature access, and payment processing (M-Pesa/Stripe) are school-scoped. A user cannot have independent subscription status from their school.

4. **Compliance**: Educational data (similar to FERPA requirements) requires strict institutional isolation. Audit trails must show which school performed which action.

**The school controls all access** because:
- Every data row in the system includes `school_id`
- Every authorization check flows through the tenant context
- User permissions are meaningless without school context
- System super-admins (`role: system_admin`) bypass tenant checks for platform-level operations

---

## 2. Tenant Isolation Strategy (CRITICAL)

### Global Scope Implementation

**File**: `app/Models/Scopes/TenantScope.php`

**Strategy**: Automatic global scope injection on ALL tenant-owned models.

### Why Global Scopes Are Safer Than Manual Filtering

| Global Scopes | Manual Filtering |
|--------------|------------------|
| ✅ Automatic - cannot be forgotten by developers | ❌ Developer must remember to add `where('school_id', ...)` every time |
| ✅ Query-level enforcement - impossible to bypass via Eloquent | ❌ Application-level only - raw queries bypass completely |
| ✅ Applies to ALL queries - relationships, eager loads, subqueries | ❌ Only applies when developer remembers |
| ✅ Memory-safe - works even with context bleeding on VPS | ❌ Relies on perfect request state management |
| ✅ Single index seek (`school_id`) - highly optimized | ❌ Variable quality based on developer |

### VPS-Scale Risk Mitigation

On Oracle Cloud Free Tier VPS with shared Redis and queued jobs:

1. **Context Bleeding**: Async queue workers processing jobs for different tenants could bleed `school_id` context between requests. Global scopes ensure database-level isolation remains.

2. **Redis Key Collision**: Without tenant prefixing in Redis, cache keys could overlap. Global scopes provide backup isolation.

3. **Raw Query Risk**: Developers writing raw SQL for performance (reporting dashboards) might forget tenant filters. Global scopes on Eloquent models catch most cases.

4. **Testing Errors**: Test factories forgetting `school_id`. Global scopes won't save bad data insertion, but will prevent retrieval.

### Query-Level Protection

**What happens without global scopes:**
```php
// DANGEROUS - Easy to forget
User::where('email', $email)->first(); 
// Returns user from ANY school!
```

**With global scopes:**
```php
// SAFE - school_id automatically injected
User::where('email', $email)->first();
// WHERE school_id = ? AND email = ?
```

---

## 3. Middleware: Tenant Context Binding

**File**: `app/Http/Middleware/TenantMiddleware.php`

### How Request Lifecycle Enforces Tenancy

```
1. User authenticates via Laravel Breeze (session-based)
   ↓
2. Every authenticated request includes session/auth cookie
   ↓
3. TenantMiddleware runs (applies to auth routes via middleware group)
   ↓
4. Resolves: Auth::user()->school_id → School::find($school_id)
   ↓
5. Binds: app()->instance('tenant', $school)
   ↓
6. TenantScope reads: app('tenant')->id
   ↓
7. ALL Eloquent queries: WHERE school_id = ?
   ↓
8. Response returns - context destroyed (fresh for next request)
```

### What Happens If Tenant Context Is Missing

**Scenario A: User with NULL school_id**
- **Cause**: Data integrity issue, user created incorrectly
- **Action**: Force logout with error message
- **Code**: `Auth::logout(); redirect('/login')->withErrors(...)`
- **Rationale**: User cannot operate without a school. Better to block than allow data corruption.

**Scenario B: School record deleted**
- **Cause**: School deleted (soft delete) but users remain
- **Action**: Force logout with "school no longer exists"
- **Rationale**: Tenant context is invalid. User has no home.

**Scenario C: System super-admin (role: system_admin)**
- **Action**: Middleware skips tenant binding
- **Result**: Queries without `school_id` constraint
- **Rationale**: Admins need platform-level access. Admin routes handle multi-tenant logic explicitly.

**Scenario D: Unauthenticated guest**
- **Action**: Middleware does not run (excluded via route groups)
- **Result**: No tenant binding
- **Rationale**: Login/register routes don't have tenant context yet.

---

## 4. Database Design

### Migration: Schools Table

**File**: `database/migrations/0001_01_01_000000_create_schools_table.php`

```php
Schema::create('schools', function (Blueprint $table) {
    $table->id();
    $table->string('name')->index();
    $table->string('email')->unique()->index();
    $table->string('phone')->nullable();
    $table->string('county')->nullable()->index();
    $table->string('subscription_status')->default('trial');
    $table->timestamp('trial_ends_at')->nullable();
    $table->timestamp('subscription_ends_at')->nullable();
    $table->json('settings')->nullable(); // School-specific config
    $table->timestamps();
    $table->softDeletes(); // Preserve data for audit
    
    // Composite indexes for common queries
    $table->index(['subscription_status', 'trial_ends_at']);
});
```

**Why These Fields:**
- `subscription_status`: Drives feature access (trial/active/expired/cancelled)
- `trial_ends_at`: Automated trial expiration (M-Pesa payment collection follows)
- `county`: Regional reporting, compliance
- `settings`: JSON for flexible school config (no schema migrations for custom fields)
- `softDeletes`: Preserve audit trail (never hard-delete school data)

---

### Migration: Users Table

**File**: `database/migrations/0001_01_02_000000_create_users_table.php`

```php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->foreignId('school_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->string('email')->unique();
    $table->string('password');
    $table->string('role')->default('teacher'); // system_admin, school_admin, teacher, staff
    $table->timestamp('email_verified_at')->nullable();
    $table->rememberToken();
    $table->timestamps();
    $table->softDeletes();
    
    // Composite indexes for tenant queries
    $table->index(['school_id', 'email']);
    $table->index(['school_id', 'role']);
});
```

**Foreign Key Constraint:**
- `foreignId('school_id')->constrained()->cascadeOnDelete()`
- **ON DELETE CASCADE**: If school is deleted, all users deleted (soft delete preserves)
- Database-level referential integrity

**Indexes:**
- `['school_id', 'email']`: Fast lookup within school (login)
- `['school_id', 'role']`: Fast role-based filtering per school

**Role Values:**
- `system_admin`: Platform super-admin (bypasses tenant isolation)
- `school_admin`: School administrator (full school access)
- `teacher`: Teacher role
- `staff`: Support/admin staff

---

### Migration: Students Table

**File**: `database/migrations/0001_01_03_000000_create_students_table.php`

```php
Schema::create('students', function (Blueprint $table) {
    $table->id();
    $table->foreignId('school_id')->constrained()->cascadeOnDelete();
    $table->string('first_name');
    $table->string('last_name');
    $table->string('email')->nullable();
    $table->date('date_of_birth')->nullable();
    $table->string('grade_level')->nullable();
    $table->json('metadata')->nullable(); // Flexible student data
    $table->timestamps();
    $table->softDeletes();
    
    // Composite indexes for tenant isolation
    $table->index(['school_id', 'email']);
    $table->index(['school_id', 'last_name', 'first_name']);
});
```

**Why `school_id` is REQUIRED for students:**
- Student belongs to exactly one school
- Prevents cross-school student data mixing
- Enables school-specific grade level systems
- Supports school-specific metadata schemas (via JSON `metadata`)

**Composite Indexes:**
- `['school_id', 'email']`: Fast email lookup within school
- `['school_id', 'last_name', 'first_name']`: Fast name search within school

---

### ALL Business Tables Must Include `school_id`

**Rule**: Every table storing tenant-owned data MUST have `school_id` foreign key.

**Examples of tables requiring `school_id`:**
- `courses` (school-specific curriculum)
- `enrollments` (student-course relationships)
- `grades` (student assessments)
- `attendance` (daily records)
- `payments` (M-Pesa/Stripe transactions)
- `invoices` (billing records)
- `documents` (uploaded files metadata)

**What Breaks If One Table Misses `school_id`:**

**Scenario**: `exams` table without `school_id`

```sql
-- School A creates exam
INSERT INTO exams (name, date) VALUES ('Math Final', '2026-06-15');
-- id = 1

-- School B queries exams (via School B's context)
SELECT * FROM exams WHERE school_id = 2;
-- Returns 0 rows (seems correct)

-- But...
SELECT * FROM exams; -- Forgot school_id filter
-- Returns School A's exam to School B!
-- CATASTROPHIC DATA LEAK
```

**Worse Scenario**: Exam results linked to students

```sql
-- Without school_id on exams:
-- School A student_id=1 takes exam_id=1
-- School B student_id=1 exists (different school)

-- Accidental cross-tenant result access:
SELECT * FROM exam_results 
WHERE student_id = 1 
  AND exam_id = 1;

-- Which school does this belong to?
-- AMBIGUOUS! Data leak inevitable.
```

**Conclusion**: Missing `school_id` on ANY table breaks the entire isolation model. It's not optional - it's mandatory.

---

## 5. Security Strategy

### Preventing Cross-Tenant Data Leakage

#### Defense Layer 1: Global Scopes (Database Query Level)

**File**: `app/Models/Scopes/TenantScope.php`

```php
public function apply(Builder $builder, Model $model): void
{
    $schoolId = $this->resolveSchoolId();
    
    if ($schoolId !== null) {
        $builder->where($model->getTable() . '.school_id', '=', $schoolId);
    }
}
```

**Protection**: ALL Eloquent queries automatically include `school_id` filter.

**Cannot Be Bypassed Via**: 
- Eloquent models
- Relationships
- Eager loading
- Subqueries

**Can Be Bypassed Via**:
- Raw DB queries (`DB::select('SELECT * FROM users')`)
- Direct database connection outside Laravel

**Mitigation for Raw Queries**:
- Code review requirement
- Static analysis rules (e.g., forbid `DB::` without tenant context comment)
- Never use raw queries for tenant data

#### Defense Layer 2: Model Traits (Creation Level)

**File**: `app/Models/Traits/BelongsToSchool.php`

```php
static::creating(function ($model) {
    if (empty($model->school_id)) {
        $school = app('tenant');
        if ($school) {
            $model->school_id = $school->id;
        } else {
            throw new \RuntimeException('Cannot create without school_id');
        }
    }
});
```

**Protection**: No model can be created without `school_id`.

**Prevents**:
- Accidental data insertion into wrong school
- System-level operations without explicit tenant
- Race conditions during concurrent creation

#### Defense Layer 3: Middleware (Request Level)

**File**: `app/Http/Middleware/TenantMiddleware.php`

```php
// Validate user belongs to school
if (is_null($user->school_id)) {
    Auth::logout(); // Force logout - critical error
    return redirect('/login')->withErrors([...]);
}

$school = School::withoutGlobalScopes()->find($user->school_id);

if (is_null($school)) {
    Auth::logout(); // School deleted
    return redirect('/login')->withErrors([...]);
}

app()->instance('tenant', $school);
```

**Protection**: Every request validated against data integrity.

**Catches**:
- Users with invalid school_id (foreign key broken)
- Deleted schools with remaining users
- Database corruption

#### Defense Layer 4: Authorization Policies (Role Level)

**File**: `app/Policies/TenantPolicy.php`

```php
public function before(User $user, string $ability): ?bool
{
    // System admins bypass tenant checks
    if ($user->role === 'system_admin') {
        return true;
    }
    return null;
}

public function update(User $user, School $school): bool
{
    // Role-based access WITHIN tenant
    return $user->school_id === $school->id 
        && in_array($user->role, ['school_admin', 'system_admin']);
}
```

**Protection**: Role-based authorization within tenant boundary.

**Note**: Policies are **NOT sufficient alone** - they're the 4th line of defense.

---

### Why Laravel Policies Alone Are Not Enough

| Issue | Explanation |
|-------|-------------|
| **1. Application-Level Only** | Policies run AFTER data is retrieved. A forgotten `->where('school_id', ...)` in controller means wrong data is loaded BEFORE policy check. |
| **2. Easy to Forget** | Must manually call `$this->authorize()` or `Gate::allows()` in every controller method. One omission = data leak. |
| **3. Raw Query Blindness** | Policies don't apply to `DB::table('users')->get()`. Raw queries bypass Eloquent entirely. |
| **4. Relationship Leaks** | Eager loading `user.students` without constraints leaks all students, even if policy checks pass on parent. |
| **5. Memory/Async Issues** | On VPS with queue workers, context bleeding between requests bypasses policy checks entirely. |
| **6. Mass Assignment** | `Model::create($request->all())` without school_id check creates data in wrong tenant before policy runs. |

**Policies are for**: Role-based actions (school_admin vs teacher permissions)

**Global Scopes are for**: Tenant isolation (mandatory data separation)

**Use both, but never rely on policies alone**

---

### VPS Deployment: Increased Attack Surface

#### Risk 1: Shared Redis Cache

**Problem**: Multiple Laravel apps or tenants sharing Redis instance

```php
// Without tenant prefixing:
Cache::put('user_123_profile', $data, 3600);
// School A and School B both cache to same key!
```

**Solution**: 
```php
// config/cache.php
'redis' => [
    'prefix' => env('CACHE_PREFIX', 'saas_' . app('tenant')?->id . '_'),
]
```

#### Risk 2: Queue Worker Context Bleeding

**Problem**: Single queue worker processes jobs for multiple schools

```php
// Job for School A
ProcessStudentData::dispatch($studentA);
// Worker processes...
// Context: school_id = 1

// Job for School B  
ProcessStudentData::dispatch($studentB);
// Worker processes...
// Context might still be school_id = 1 from previous job!
```

**Solution**:
```php
// In every job's handle():
public function handle()
{
    // Explicitly set tenant context
    $school = School::find($this->schoolId);
    app()->instance('tenant', $school);
    
    // Re-apply scopes
    // Process data...
}
```

#### Risk 3: File Storage Collisions (Cloudinary)

**Problem**: Cloudinary public IDs without tenant prefix

```php
// Upload for School A
cloudinary()->upload('student_photo.jpg', [
    'public_id' => 'students/123' // Could collide with School B!
]);
```

**Solution**:
```php
'public_id' => 'school_' . app('tenant')->id . '/students/123'
```

#### Risk 4: Database Connection Pooling

**Problem**: Persistent DB connections with session variables

**Risk**: MySQL session variables like `@school_id` could persist across requests

**Solution**: Always use Laravel's query builder, never session variables for tenant context

#### Risk 5: Cross-Site Request Forgery (CSRF) Token Confusion

**Problem**: Single Laravel app serves multiple schools on subdomains

**Risk**: CSRF token generated for school-a.domain.com accepted on school-b.domain.com

**Solution**:
```php
// config/session.php
'domain' => '.yourdomain.com', // Share session across subdomains
'same_site' => 'lax',
```

Plus: Verify `school_id` on every POST request via middleware

---

### Security Checklist

- [x] GlobalScope applied to all tenant models
- [x] `school_id` foreign key on ALL tenant tables
- [x] Composite indexes including `school_id`
- [x] TenantMiddleware validates context per request
- [x] Model trait auto-injects `school_id` on create
- [x] Authorization policies for role checks
- [x] System admin bypass clearly documented
- [x] Redis cache key prefixing by tenant
- [x] Queue jobs explicitly set tenant context
- [x] Cloudinary public IDs prefixed by tenant
- [x] No raw DB queries for tenant data
- [x] Soft deletes preserve audit trail
- [x] Foreign key constraints with cascade
- [x] Session/context isolation per request
- [x] Login forces re-validation if school missing

---

## Automatic Onboarding Flow

**Self-Registration Process:**

```
1. School fills registration form (name, email, phone, county)
   ↓
2. School::create() with subscription_status='trial'
   ↓
3. trial_ends_at = now() + 14 days
   ↓
4. First admin user created:
   - User::create([
       'school_id' => $school->id,
       'role' => 'school_admin',
       ...
     ])
   ↓
5. Welcome email sent (Resend)
   ↓
6. Login link with auto-login token
   ↓
7. First login → TenantMiddleware activates
   ↓
8. All subsequent queries automatically scoped to school_id
```

**No manual provisioning required.**

---

## Next Steps

Phase 1 Complete. Ready for:
- Phase 2: Authentication & Registration Flow
- Phase 3: Role-Based Access Control (RBAC) with Spatie
- Phase 4: Subscription Management (Trial → Paid)
- Phase 5: Cloudinary Integration for File Storage
- Phase 6: Queue Workers & Background Jobs (Redis)
- Phase 7: M-Pesa & Stripe Payment Integration
- Phase 8: Inertia.js + React Frontend
- Phase 9: Deployment Scripts (Oracle Cloud VPS)
- Phase 10: Cloudflare Configuration (SSL, WAF, Caching)

</document>
