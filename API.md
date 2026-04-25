# SaaS School System - API Documentation

## Overview

Multi-tenant SaaS platform for school management with Laravel 11, Inertia.js, and React. All tenant-owned data is scoped by `school_id` foreign key enforced via GlobalScope.

## Base URL

```
https://saas.yourdomain.com
```

## Authentication

### Login

```http
POST /login
Content-Type: application/json

{
  "email": "user@school.edu",
  "password": "secret",
  "remember": false
}
```

**Response:** Redirect to `/dashboard` (Inertia.js)

### Registration (School Self-Service)

```http
POST /register
Content-Type: application/json

{
  "school_name": "St. Mary's Academy",
  "school_email": "admin@school.edu",
  "school_county": "Nairobi",
  "name": "John Doe",
  "email": "john@school.edu",
  "phone": "+254700000000",
  "password": "secret",
  "password_confirmation": "secret"
}
```

**Response:** Creates School + User (school_admin) + 14-day trial, auto-login

### Logout

```http
POST /logout
```

**Response:** Redirect to `/`

---

## Tenant Isolation

All authenticated routes include `school_id` constraint via GlobalScope.

### Middleware Stack

- `web`: Session, CSRF, encryption
- `auth`: Authentication check
- `tenant`: Binds school context (every request)

---

## Models

### School (Tenant Root)

```json
{
  "id": 1,
  "name": "St. Mary's Academy",
  "email": "admin@school.edu",
  "phone": "+254700000000",
  "county": "Nairobi",
  "subscription_status": "active",
  "trial_ends_at": "2026-05-09T00:00:00Z",
  "settings": {
    "timezone": "Africa/Nairobi",
    "currency": "KES"
  }
}
```

### User

```json
{
  "id": 1,
  "school_id": 1,
  "name": "John Doe",
  "email": "john@school.edu",
  "role": "school_admin"
}
```

**Roles:**
- `system_admin`: Platform-level (bypasses tenant isolation)
- `school_admin`: Full school access
- `teacher`: Manage assigned courses, grades, attendance
- `staff`: Limited access

### Student

```json
{
  "id": 1,
  "school_id": 1,
  "first_name": "Jane",
  "last_name": "Smith",
  "email": "jane.smith@school.edu",
  "date_of_birth": "2010-05-15",
  "grade_level": "Grade 8",
  "metadata": {
    "parent_contact": "+254711000000"
  }
}
```

### Subscription

```json
{
  "id": 1,
  "school_id": 1,
  "plan_type": "basic",
  "status": "active",
  "starts_at": "2026-04-01T00:00:00Z",
  "ends_at": "2026-05-01T00:00:00Z",
  "amount_paid": 2999,
  "currency": "KES"
}
```

**Plans:**
| Plan | Price | Students | Features |
|------|-------|----------|----------|
| Trial | Free | 100 | Basic attendance, grades, 5 staff |
| Basic | 2,999 KES/mo | 500 | Full attendance, grades, reports, unlimited staff |
| Premium | 7,999 KES/mo | 2,000 | SMS notifications, parent portal |
| Enterprise | 19,999 KES/mo | Unlimited | API access, custom reports, priority support |

### Payment

```json
{
  "id": 1,
  "school_id": 1,
  "subscription_id": 1,
  "amount": 2999,
  "currency": "KES",
  "provider": "mpesa",
  "status": "completed",
  "paid_at": "2026-04-01T10:30:00Z"
}
```

**Providers:** `stripe`, `mpesa`, `manual`, `bank_transfer`

### Invoice

```json
{
  "id": 1,
  "school_id": 1,
  "invoice_number": "INV-202604-000001",
  "amount": 2999,
  "currency": "KES",
  "status": "paid",
  "due_date": "2026-05-01",
  "line_items": [
    {
      "description": "Basic Plan Subscription",
      "amount": 2999,
      "quantity": 1
    }
  ]
}
```

---

## Authorization

### Spatie Permissions

All permission checks are scoped to `school_id`.

**Permission Groups:**

1. **Students**
   - `view students`
   - `create students`
   - `edit students`
   - `delete students`
   - `export students`

2. **Attendance**
   - `view attendance`
   - `mark attendance`
   - `edit attendance`
   - `export attendance`

3. **Grades**
   - `view grades`
   - `create grades`
   - `edit grades`
   - `delete grades`
   - `export grades`

4. **Courses**
   - `view courses`
   - `create courses`
   - `edit courses`
   - `delete courses`
   - `assign teachers`

5. **Billing**
   - `view billing`
   - `create payments`
   - `refund payments`
   - `update subscription`
   - `manage payment methods`

6. **Users**
   - `view users`
   - `create users`
   - `edit users`
   - `delete users`
   - `assign roles`

7. **Settings**
   - `view settings`
   - `edit settings`
   - `manage storage`

8. **Reports**
   - `view reports`
   - `export reports`

### Gates

```php
// Check permission
Gate::allows('has-permission', 'view students');

// Check role
Gate::allows('has-role', 'school_admin');

// Check tenant ownership
Gate::allows('owns-tenant', $school);

// Admin or higher
Gate::allows('admin-or-higher');

// Multiple permissions
Gate::allows('has-any-permission', ['create users', 'edit users']);

// Multiple roles
Gate::allows('has-any-role', ['school_admin', 'teacher']);
```

---

## Webhooks

### Stripe

```http
POST /stripe/webhook
Content-Type: application/json
Stripe-Signature: t=...,v1=...,v0=...

{ "type": "customer.subscription.updated", ... }
```

**Events:**
- `customer.subscription.created`
- `customer.subscription.updated`
- `customer.subscription.deleted`
- `invoice.payment_succeeded`
- `invoice.payment_failed`

### M-Pesa

```http
POST /webhook/mpesa/callback
Content-Type: application/json

{
  "ResultCode": "0",
  "ResultDesc": "Success",
  "TransID": "LGT2026040100000",
  "TransAmount": "2999",
  "TransTime": "20260401000000"
}
```

**Verification:**
All webhook requests are validated with provider signatures.

---

## File Uploads (Cloudinary)

All uploads include tenant namespace in public ID.

### Upload Student Photo

```http
POST /students/{id}/photo
Content-Type: multipart/form-data
```

**Public ID Format:** `school_{school_id}/students/{student_id}/profile`

**Transformations:** 300x300 crop, face gravity

### Upload Document

```http
POST /documents/upload
Content-Type: multipart/form-data

{
  "type": "certificate",
  "title": "Grade Transcript 2026"
}
```

**Public ID Format:** `school_{school_id}/documents/{type}/{slug}`

---

## Queue Jobs

### ProcessSubscriptionWebhook

```php
ProcessSubscriptionWebhook::dispatch(
    schoolId: 1,
    webhookData: $payload,
    provider: 'stripe'
);
```

**Handled by:** Supervisor-managed queue workers (3x)

**Retries:** 3 attempts, 60s timeout

---

## Rate Limiting

### Login

- 5 attempts per minute per email + IP

### API

Configured via `RouteServiceProvider`

---

## Security

### CORS

```
Allowed Origins: *
Allowed Methods: *
Allowed Headers: *
Supports Credentials: true
```

### CSRF

- Enabled on all state-changing requests
- Excluded: `stripe/*`, `webhook/mpesa/*`, `api/*`
- Mismatch logging with tenant context

### Session

- Driver: Redis
- Lifetime: 120 minutes
- Cookie: `school_{school_id}_session`
- Secure: true (HTTPS only)
- HttpOnly: true
- SameSite: lax

### Cache

- Driver: Redis
- Prefix: `saas_{school_id}_`

---

## Scheduling

### Hourly

- Subscription expiration check

### Daily

- Failed job retry cleanup

### Weekly

- Queue optimization

---

## Logging

### Channels

- `stack` (daily): Application logs
- `worker` (daily): Queue worker logs
- `schedule` (daily): Scheduler logs

### Monolog

```php
Log::info('Subscription processed', [
    'school_id' => $school->id,
    'subscription_id' => $subscription->id,
]);
```

---

## Error Handling

### HTTP Exceptions

| Code | Description |
|------|-------------|
| 401 | Unauthorized |
| 403 | Forbidden (tenant boundary) |
| 404 | Not Found |
| 422 | Validation Error |
| 429 | Too Many Requests |
| 500 | Server Error |

### Custom Exceptions

```php
// Tenant context missing
throw new \RuntimeException('Cannot create without school_id');

// Subscription expired
if ($school->subscription->isExpired()) {
    throw new SubscriptionExpiredException();
}
```

---

## Deployment

### Environment Variables

See `deploy/README.md` for full configuration.

### Required

```bash
APP_KEY=base64:...
APP_ENV=production
APP_DEBUG=false

DB_CONNECTION=pgsql
DB_HOST=...
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

REDIS_HOST=127.0.0.1

CLOUDINARY_URL=cloudinary://...

STRIPE_KEY=pk_live_...
STRIPE_SECRET=sk_live_...

RESEND_KEY=re_live_...
```

---

## Testing

### Run Tests

```bash
php artisan test

# Specific suite
php artisan test --filter=TenantIsolationTest
php artisan test --filter=SubscriptionBillingTest
```

### Factories

```php
School::factory()->create();
User::factory()->create(['school_id' => $school->id]);
Student::factory()->count(5)->create(['school_id' => $school->id]);
```

---

## Support

- **Documentation:** `docs/` directory
- **Deployment:** `deploy/README.md`
- **Issues:** GitHub Issues
- **Security:** security@saas-system.com
