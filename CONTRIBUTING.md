# Contributing Guide

## Getting Started

### Prerequisites

- PHP 8.2+
- Composer
- Node.js 20+
- npm or yarn
- PostgreSQL (local or Supabase/Neon)
- Redis

### Installation

```bash
# Clone repository
git clone https://github.com/Morgan-Okoth/saas-system.git
cd saas-system

# Install PHP dependencies
composer install

# Install Node dependencies
npm install
npm run build

# Environment setup
cp .env.example .env
php artisan key:generate

# Update .env with your database credentials
# DB_CONNECTION=pgsql
# DB_HOST=127.0.0.1
# DB_PORT=5432
# DB_DATABASE=saas
# DB_USERNAME=your_user
# DB_PASSWORD=your_password

# Run migrations
php artisan migrate

# Seed permissions
php artisan db:seed --class=PermissionSeeder

# Start development server
php artisan serve
```

## Architecture

### Multi-Tenancy

**School = Tenant Root**

Every tenant-owned model includes `school_id` foreign key:
- `users.school_id` → `schools.id`
- `students.school_id` → `schools.id`
- All business tables follow this pattern

**Global Scope Enforcement**

```php
// Automatically applied to all tenant models
$builder->where('school_id', '=', $currentSchoolId);
```

**Tenant Context**

Bound via `App\Http\Middleware\TenantMiddleware`:
```php
app()->instance('tenant', $school);
```

Access anywhere:
```php
$school = app('tenant');
```

### Security Layers

1. **Database-Level**: GlobalScope (query filtering)
2. **Creation-Level**: BelongsToSchool trait (auto-inject school_id)
3. **Request-Level**: TenantMiddleware (context binding)
4. **Authorization-Level**: Spatie Permissions (role-based)

### RBAC

**Permissions** → **Roles** → **Users**

- Permissions are school-scoped
- Roles group permissions
- Users have roles per school
- System admin (role=system_admin) bypasses tenant isolation

**Example:**
```php
// Assign permission to role
$role = Role::where('school_id', $school->id)
    ->where('name', 'school_admin')
    ->first();
$role->givePermissionTo('create students');

// Assign role to user
$user->assignRole('school_admin');

// Check permission
$user->can('create students');
$user->hasRole('school_admin');
```

## Development Workflow

### Branching Strategy

- `main` → Production-ready code
- `feature/*` → New features
- `fix/*` → Bug fixes

### Commit Messages

```
Phase X: Description

- Brief summary of changes
- Bullet points for details
- Reference issues if applicable
```

### Testing

```bash
# Run all tests
php artisan test

# Run specific test class
php artisan test --filter=TenantIsolationTest

# Run with coverage (if configured)
php artisan test --coverage
```

**Test Structure:**
- `tests/Feature/` → HTTP and integration tests
- `tests/Unit/` → Unit tests
- `tests/TestCase.php` → Base test case

**Factory Usage:**
```php
School::factory()->create();
User::factory()->create(['school_id' => $school->id]);
Student::factory()->count(5)->create(['school_id' => $school->id]);
```

### Code Style

- PSR-12 coding standards
- Use Laravel conventions
- Type hints where possible
- DocBlocks for public methods

**Auto-format:**
```bash
composer run format
```

### Database Migrations

**Naming:**
```
0001_01_01_000000_create_schools_table.php
YYYY_MM_DD_HHMMSS_description.php
```

**Foreign Keys:**
```php
$table->foreignId('school_id')->constrained()->cascadeOnDelete();
```

**Indexes:**
```php
$table->index(['school_id', 'email']);
```

**Soft Deletes:**
```php
use SoftDeletes;
$table->softDeletes();
```

### Security

**Never:**
- Skip tenant scope in queries
- Use raw DB queries for tenant data
- Forget to validate tenant context
- Expose cross-tenant data in APIs
- Hardcode secrets in code

**Always:**
- Use Eloquent with GlobalScope
- Validate school_id on creates
- Check auth with `Gate::allows()`
- Log security events (CSRF failures)
- Use HTTPS in production
- Hash passwords with bcrypt

**CSRF Protection:**
- Enabled by default
- Excludes webhook endpoints
- Token verified automatically

## Frontend (Inertia.js + React)

### Pages

```
resources/js/Pages/
├── Welcome.jsx
├── Auth/
│   ├── Login.jsx
│   └── Register.jsx
├── Dashboard.jsx
└── School/
    └── Edit.jsx
```

**Layouts:**
```
resources/js/Layouts/
├── GuestLayout.jsx
└── AuthenticatedLayout.jsx
```

### Data Fetching

```jsx
// In controller
return inertia('Dashboard', {
    school: $school,
    stats: $stats,
});

// In component
export default function Dashboard({ school, stats }) {
    return <div>{school.name}</div>;
}
```

### API Requests

```jsx
import { useForm } from '@inertiajs/react';

const { data, setData, post, processing } = useForm({
    email: '',
    password: '',
});

const handleSubmit = (e) => {
    e.preventDefault();
    post('/login');
};
```

### File Uploads

```jsx
<input 
    type="file" 
    onChange={(e) => setData('photo', e.target.files[0])}
/>
```

Uploads go to Cloudinary with tenant-scoped public ID.

## Billing

### Subscription Lifecycle

```
trial (14 days) → active → expired/cancelled
              → past_due (payment failed)
```

### Webhook Handling

**Stripe Events:**
- `customer.subscription.created` → Activate subscription
- `customer.subscription.updated` → Update status
- `customer.subscription.deleted` → Cancel subscription
- `invoice.payment_succeeded` → Record payment, extend subscription
- `invoice.payment_failed` → Mark past due, notify admin

**M-Pesa Events:**
- `ResultCode = 0` → Payment successful
- `ResultCode ≠ 0` → Payment failed

**Queue Workers:**
- Process webhooks asynchronously
- Retry failed webhooks (3×)
- Timeout: 60s

### Testing Payments

**Stripe Test Cards:**
- `4242 4242 4242 4242` → Success
- `4000 0000 0000 9995` → Insufficient funds

**M-Pesa Test:**
- Use Lipa Na M-Pesa sandbox

## Deployment

### Production

See `deploy/README.md` for full guide.

**Requirements:**
- Ubuntu 22.04+
- PHP 8.2
- PostgreSQL
- Redis
- Nginx
- SSL (Cloudflare)

**Deploy:**
```bash
bash deploy/deploy.sh main production
```

**Setup Workers:**
```bash
bash deploy/supervisor-setup.sh
```

### Environment Variables

**Critical:**
```bash
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...

DB_CONNECTION=pgsql
DB_HOST=...
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

REDIS_HOST=127.0.0.1

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

CLOUDINARY_URL=cloudinary://...

STRIPE_KEY=pk_live_...
STRIPE_SECRET=sk_live_...

SESSION_SECURE_COOKIE=true
```

### Security Checklist

- [x] HTTPS enforced (Cloudflare)
- [x] CSRF protection enabled
- [x] Session cookies: Secure, HttpOnly
- [x] CORS configured
- [x] Rate limiting on login
- [x] Tenant isolation verified
- [x] No debug in production
- [x] Database backups automated
- [x] Queue workers monitored

## Monitoring

### Logs

```bash
# Application logs
tail -f storage/logs/laravel.log

# Queue worker logs
tail -f storage/logs/worker.log

# Scheduler logs
tail -f storage/logs/schedule.log

# Nginx logs
tail -f /var/log/nginx/saas_access.log
tail -f /var/log/nginx/saas_error.log
```

### Health Check

```bash
curl https://saas.yourdomain.com/health
```

Returns JSON with Redis, DB status.

### Telescope (Optional)

```bash
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate
```

Access: `/telescope`

## Troubleshooting

### Queue Workers Not Processing

```bash
sudo supervisorctl status
sudo supervisorctl restart saas-worker:*
php artisan queue:restart
```

### Database Connection Failed

```bash
php artisan migrate --pretend
php artisan tinker
>>> DB::connection()->getPdo();
```

### Redis Connection

```bash
redis-cli ping
sudo systemctl status redis-server
```

### CSRF Token Mismatch

- Clear browser cookies
- Check session domain
- Verify HTTPS/HTTP consistency

### Permission Denied

```bash
sudo chown -R www-data:www-data /var/www/saas
sudo chmod -R 775 /var/www/saas/storage
```

## Contributing Code

### Steps

1. Fork repository
2. Create feature branch: `git checkout -b feature/name`
3. Make changes
4. Run tests: `php artisan test`
5. Commit: `git commit -m "Phase X: Description"`
6. Push: `git push origin feature/name`
7. Open Pull Request

### Code Review Checklist

- [ ] Follows PSR-12
- [ ] Tenant isolation maintained
- [ ] Tests added/updated
- [ ] No raw DB queries for tenant data
- [ ] Security implications considered
- [ ] Documentation updated
- [ ] CI passes

### Reporting Issues

Include:
- Steps to reproduce
- Expected vs actual behavior
- Laravel logs
- Screenshots (if applicable)
- Environment details

## License

Proprietary - All rights reserved.

## Support

- GitHub Issues: https://github.com/Morgan-Okoth/saas-system/issues
- Email: support@saas-system.com
- Documentation: `docs/` directory
