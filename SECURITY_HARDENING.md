# Security Hardening Implementation Plan

## Overview
This document tracks the implementation of security hardening features for the Sales Dashboard application. The goal is to restrict access to authorized business users only, enforce strong passwords, and provide admin controls for security settings.

## Architecture Decisions

### ✅ Database-Driven Settings (Not ENV)
- Dynamic configuration without deployments
- Built-in audit trail
- Admin UI for non-technical users
- Easy backup/restore

### ✅ Simple `is_admin` Flag + Laravel Gates/Policies
- Lightweight solution for small team
- Use `$user->can('manage-security')` checks
- No extra packages needed
- Laravel's built-in authorization system

### ✅ Business Email Domain Validation
- Database allow-list for approved domains
- Support for one-off email exceptions
- Admin-managed through settings UI
- Custom validation rule: `App\Rules\BusinessEmailDomain`

### ✅ Strong Password Requirements
- Configured via `Password::defaults()` in AppServiceProvider
- 12+ characters, mixed case, numbers, symbols
- Breach detection via `uncompromised()` check

---

## Implementation Phases

### Phase 1: Strong Password Requirements ⏱️ 10 minutes

**Files to Modify:**
- `app/Providers/AppServiceProvider.php`
- `resources/views/livewire/auth/register.blade.php` (messaging)
- `resources/views/livewire/auth/reset-password.blade.php` (messaging)

**Code Example:**
```php
// app/Providers/AppServiceProvider.php
use Illuminate\Validation\Rules\Password;

public function boot(): void
{
    Password::defaults(fn () => Password::min(12)
        ->letters()           // Must contain letters
        ->mixedCase()         // Upper AND lowercase
        ->numbers()           // At least one number
        ->symbols()           // At least one special character
        ->uncompromised()     // Check against pwned passwords database
    );
}
```

**UI Messaging:**
```blade
<flux:field>
    <flux:label>Password</flux:label>
    <flux:input type="password" wire:model="password" />
    <flux:description>
        Minimum 12 characters with uppercase, lowercase, numbers, and symbols
    </flux:description>
    <flux:error name="password" />
</flux:field>
```

**Testing:**
- ✅ Password less than 12 chars should fail
- ✅ Password without symbols should fail
- ✅ Password without mixed case should fail
- ✅ Common passwords should fail (uncompromised check)

---

### Phase 2: Database Settings Infrastructure ⏱️ 45 minutes

**Files to Create:**
- `app/Models/AppSetting.php`
- `app/Services/SettingsService.php`
- `database/migrations/YYYY_MM_DD_create_app_settings_table.php`
- `database/seeders/SecuritySettingsSeeder.php`

**Migration:**
```php
Schema::create('app_settings', function (Blueprint $table) {
    $table->id();
    $table->string('key')->unique();
    $table->json('value');
    $table->string('type')->default('general'); // security, features, integrations
    $table->text('description')->nullable();
    $table->foreignId('updated_by')->nullable()->constrained('users');
    $table->timestamps();
});
```

**AppSetting Model:**
```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppSetting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'description', 'updated_by'];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
```

**SettingsService:**
```php
namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember("setting:{$key}", 3600, function () use ($key, $default) {
            $setting = AppSetting::where('key', $key)->first();
            return $setting ? $setting->value : $default;
        });
    }

    public function set(string $key, mixed $value, ?int $userId = null): void
    {
        AppSetting::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'updated_by' => $userId,
            ]
        );

        Cache::forget("setting:{$key}");
    }

    public function getArray(string $key): array
    {
        return (array) $this->get($key, []);
    }

    public function getBool(string $key, bool $default = false): bool
    {
        return (bool) $this->get($key, $default);
    }
}
```

**Default Security Settings (Seeder):**
```php
AppSetting::create([
    'key' => 'security.allowed_domains',
    'value' => ['example.com'], // Replace with your domain
    'type' => 'security',
    'description' => 'Allowed email domains for user registration',
]);

AppSetting::create([
    'key' => 'security.allowed_emails',
    'value' => [], // Individual email exceptions
    'type' => 'security',
    'description' => 'Individual email addresses allowed outside approved domains',
]);

AppSetting::create([
    'key' => 'security.enforce_verification',
    'value' => true,
    'type' => 'security',
    'description' => 'Require email verification before accessing the app',
]);
```

**Testing:**
- ✅ Can store and retrieve settings
- ✅ Settings are cached
- ✅ Cache invalidates on update
- ✅ Array/bool helpers work correctly

---

### Phase 3: Business Email Domain Validation ⏱️ 30 minutes

**Files to Create:**
- `app/Rules/BusinessEmailDomain.php`

**Files to Modify:**
- `app/Livewire/Auth/Register.php`

**BusinessEmailDomain Rule:**
```php
namespace App\Rules;

use App\Services\SettingsService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class BusinessEmailDomain implements ValidationRule
{
    public function __construct(
        private readonly SettingsService $settings
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $email = strtolower($value);

        // Check if specific email is allowed
        $allowedEmails = $this->settings->getArray('security.allowed_emails');
        if (in_array($email, array_map('strtolower', $allowedEmails))) {
            return;
        }

        // Extract domain from email
        $domain = substr(strrchr($email, "@"), 1);

        // Check if domain is allowed
        $allowedDomains = $this->settings->getArray('security.allowed_domains');
        $allowedDomains = array_map('strtolower', $allowedDomains);

        if (!in_array($domain, $allowedDomains)) {
            $fail('Registration is restricted to authorized business email addresses.');
        }
    }
}
```

**Update Register Component:**
```php
use App\Rules\BusinessEmailDomain;
use App\Services\SettingsService;

public function __construct(
    private readonly SettingsService $settings
) {}

public function register(): void
{
    $validated = $this->validate([
        'name' => ['required', 'string', 'max:255'],
        'email' => [
            'required',
            'string',
            'lowercase',
            'email',
            'max:255',
            'unique:'.User::class,
            new BusinessEmailDomain($this->settings),
        ],
        'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
    ]);

    // ... rest of registration logic
}
```

**Testing:**
- ✅ Emails from allowed domains pass validation
- ✅ Emails from disallowed domains fail validation
- ✅ Specific allowed emails pass even if domain not allowed
- ✅ Case-insensitive validation
- ✅ Proper error message displayed

---

### Phase 4: Admin Authorization System ⏱️ 30 minutes

**Files to Create:**
- `database/migrations/YYYY_MM_DD_add_is_admin_to_users_table.php`
- `app/Policies/SecuritySettingsPolicy.php` (optional, or use Gate)

**Files to Modify:**
- `app/Providers/AppServiceProvider.php` (register gates)
- `app/Models/User.php`

**Migration:**
```php
Schema::table('users', function (Blueprint $table) {
    $table->boolean('is_admin')->default(false)->after('email_verified_at');
});
```

**User Model Helper:**
```php
// app/Models/User.php

protected function casts(): array
{
    return [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_admin' => 'boolean',
    ];
}

// Helper attribute
protected function canManageSecurity(): Attribute
{
    return Attribute::make(
        get: fn () => $this->is_admin
    );
}
```

**Register Gates in AppServiceProvider:**
```php
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    // Password configuration
    Password::defaults(fn () => Password::min(12)
        ->letters()
        ->mixedCase()
        ->numbers()
        ->symbols()
        ->uncompromised()
    );

    // Security management gate
    Gate::define('manage-security', function (User $user) {
        return $user->is_admin;
    });

    Gate::define('manage-users', function (User $user) {
        return $user->is_admin;
    });
}
```

**Usage in Livewire Components:**
```php
// Check authorization
if (!auth()->user()->can('manage-security')) {
    abort(403);
}

// Or in Blade
@can('manage-security')
    <flux:button wire:click="deleteUser">Delete User</flux:button>
@endcan
```

**Testing:**
- ✅ Admin users can access security settings
- ✅ Non-admin users get 403
- ✅ Gates work correctly in views
- ✅ Gates work correctly in Livewire components

---

### Phase 5: Admin Security Settings UI ⏱️ 1 hour

**Files to Create:**
- `app/Livewire/Settings/SecuritySettings.php`
- `resources/views/livewire/settings/security-settings.blade.php`

**Files to Modify:**
- `routes/web.php`
- `resources/views/components/layouts/settings.blade.php` (add nav item)

**Route:**
```php
Route::middleware(['auth', 'can:manage-security'])->group(function () {
    Route::get('settings/security', SecuritySettings::class)->name('settings.security');
});
```

**SecuritySettings Component:**
```php
namespace App\Livewire\Settings;

use App\Services\SettingsService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.settings')]
class SecuritySettings extends Component
{
    public array $allowedDomains = [];
    public array $allowedEmails = [];
    public string $newDomain = '';
    public string $newEmail = '';

    public function __construct(
        private readonly SettingsService $settings
    ) {}

    public function mount(): void
    {
        $this->allowedDomains = $this->settings->getArray('security.allowed_domains');
        $this->allowedEmails = $this->settings->getArray('security.allowed_emails');
    }

    public function addDomain(): void
    {
        $this->validate([
            'newDomain' => ['required', 'string', 'regex:/^[a-zA-Z0-9][a-zA-Z0-9-]{0,61}[a-zA-Z0-9]?\.[a-zA-Z]{2,}$/'],
        ]);

        $domain = strtolower(trim($this->newDomain));

        if (!in_array($domain, $this->allowedDomains)) {
            $this->allowedDomains[] = $domain;
            $this->settings->set('security.allowed_domains', $this->allowedDomains, auth()->id());
        }

        $this->newDomain = '';
    }

    public function removeDomain(string $domain): void
    {
        $this->allowedDomains = array_values(array_filter(
            $this->allowedDomains,
            fn($d) => $d !== $domain
        ));

        $this->settings->set('security.allowed_domains', $this->allowedDomains, auth()->id());
    }

    public function addEmail(): void
    {
        $this->validate([
            'newEmail' => ['required', 'email'],
        ]);

        $email = strtolower(trim($this->newEmail));

        if (!in_array($email, $this->allowedEmails)) {
            $this->allowedEmails[] = $email;
            $this->settings->set('security.allowed_emails', $this->allowedEmails, auth()->id());
        }

        $this->newEmail = '';
    }

    public function removeEmail(string $email): void
    {
        $this->allowedEmails = array_values(array_filter(
            $this->allowedEmails,
            fn($e) => $e !== $email
        ));

        $this->settings->set('security.allowed_emails', $this->allowedEmails, auth()->id());
    }

    public function render()
    {
        return view('livewire.settings.security-settings');
    }
}
```

**View Template Structure:**
```blade
<div>
    <flux:heading size="xl">Security Settings</flux:heading>
    <flux:subheading>Manage allowed email domains and security policies</flux:subheading>

    {{-- Allowed Domains Section --}}
    <flux:card class="mt-6">
        <flux:heading size="lg">Allowed Email Domains</flux:heading>
        <flux:subheading>Only users with these email domains can register</flux:subheading>

        <div class="space-y-2 mt-4">
            @foreach($allowedDomains as $domain)
                <flux:badge>
                    {{ $domain }}
                    <flux:button icon="x-mark" size="xs" wire:click="removeDomain('{{ $domain }}')" />
                </flux:badge>
            @endforeach
        </div>

        <flux:field class="mt-4">
            <flux:label>Add Domain</flux:label>
            <div class="flex gap-2">
                <flux:input wire:model="newDomain" placeholder="example.com" />
                <flux:button wire:click="addDomain">Add</flux:button>
            </div>
            <flux:error name="newDomain" />
        </flux:field>
    </flux:card>

    {{-- Allowed Individual Emails Section --}}
    <flux:card class="mt-6">
        <flux:heading size="lg">Allowed Individual Emails</flux:heading>
        <flux:subheading>Specific email addresses allowed outside approved domains</flux:subheading>

        <div class="space-y-2 mt-4">
            @foreach($allowedEmails as $email)
                <flux:badge>
                    {{ $email }}
                    <flux:button icon="x-mark" size="xs" wire:click="removeEmail('{{ $email }}')" />
                </flux:badge>
            @endforeach
        </div>

        <flux:field class="mt-4">
            <flux:label>Add Email</flux:label>
            <div class="flex gap-2">
                <flux:input wire:model="newEmail" type="email" placeholder="user@example.com" />
                <flux:button wire:click="addEmail">Add</flux:button>
            </div>
            <flux:error name="newEmail" />
        </flux:field>
    </flux:card>
</div>
```

**Add to Settings Navigation:**
```blade
{{-- In settings layout --}}
@can('manage-security')
    <flux:navlist.item icon="shield-check" href="{{ route('settings.security') }}">
        Security
    </flux:navlist.item>
@endcan
```

**Testing:**
- ✅ Admin can access security settings
- ✅ Can add/remove domains
- ✅ Can add/remove individual emails
- ✅ Invalid domains are rejected
- ✅ Changes persist to database
- ✅ Settings are used in registration validation

---

### Phase 6: Rate Limiting & Additional Security ⏱️ 30 minutes

**Files to Modify:**
- `bootstrap/app.php` (configure throttle)
- `routes/auth.php`

**Custom Rate Limiters:**
```php
// bootstrap/app.php
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Configure rate limiters
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->input('email').$request->ip());
        });

        RateLimiter::for('register', function (Request $request) {
            return Limit::perHour(3)->by($request->ip());
        });
    })
    ->create();
```

**Apply to Auth Routes:**
```php
// routes/auth.php
Route::middleware(['throttle:login'])->group(function () {
    Route::get('login', Login::class)->name('login');
    // ... other login routes
});

Route::middleware(['throttle:register'])->group(function () {
    Route::get('register', Register::class)->name('register');
});
```

**Testing:**
- ✅ Login attempts are rate limited
- ✅ Registration attempts are rate limited
- ✅ Rate limits reset after time period

---

### Phase 7: Comprehensive Testing ⏱️ 1 hour

**Files to Create:**
- `tests/Feature/Auth/PasswordValidationTest.php`
- `tests/Feature/Auth/BusinessEmailValidationTest.php`
- `tests/Feature/Settings/SecuritySettingsTest.php`
- `tests/Feature/Authorization/AdminGateTest.php`
- `tests/Unit/Rules/BusinessEmailDomainTest.php`
- `tests/Unit/Services/SettingsServiceTest.php`

**Test Coverage:**
1. Password validation rules
2. Business email domain validation
3. Settings service CRUD operations
4. Admin gate authorization
5. Security settings UI (Livewire)
6. Rate limiting

---

## Progress Tracking

- [ ] Phase 1: Strong Password Requirements
- [ ] Phase 2: Database Settings Infrastructure
- [ ] Phase 3: Business Email Domain Validation
- [ ] Phase 4: Admin Authorization System
- [ ] Phase 5: Admin Security Settings UI
- [ ] Phase 6: Rate Limiting & Additional Security
- [ ] Phase 7: Comprehensive Testing

---

## Quick Reference

### Check if User is Admin
```php
$user->is_admin
$user->can('manage-security')
auth()->user()->can('manage-security')
```

### Get/Set Settings
```php
$settings = app(SettingsService::class);

// Get setting
$domains = $settings->getArray('security.allowed_domains');
$enforce = $settings->getBool('security.enforce_verification');

// Set setting
$settings->set('security.allowed_domains', ['example.com'], auth()->id());
```

### Use in Validation
```php
use App\Rules\BusinessEmailDomain;

$this->validate([
    'email' => ['required', 'email', new BusinessEmailDomain($settings)],
]);
```

### Blade Authorization
```blade
@can('manage-security')
    <button>Admin Only</button>
@endcan

@if(auth()->user()->can('manage-security'))
    <!-- Admin content -->
@endif
```

---

## Notes
- All sensitive settings stored in database, not ENV
- Uses Laravel's built-in authorization (Gates)
- Simple `is_admin` flag sufficient for single-tenant app
- Settings cached for performance (1 hour TTL)
- Email validation is case-insensitive
- Domain validation supports both domains and individual emails
