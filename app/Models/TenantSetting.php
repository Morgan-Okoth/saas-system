<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tenant Setting Model
 * 
 * Stores advanced tenant-specific configurations:
 * - Custom domains
 * - White-label branding
 * - Analytics settings
 * - Feature flags
 */
class TenantSetting extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id',
        'custom_domain',
        'custom_domain_verified',
        'custom_domain_verified_at',
        'ssl_certificate_path',
        'logo_path',
        'favicon_path',
        'primary_color',
        'secondary_color',
        'accent_color',
        'school_name_display',
        'footer_text',
        'analytics_enabled',
        'analytics_provider',
        'analytics_tracking_id',
        'analytics_anonymize_ip',
        'feature_parent_portal',
        'feature_sms_notifications',
        'feature_mobile_app',
        'feature_advanced_reports',
        'feature_api_access',
        'enabled_modules',
        'timezone',
        'currency',
        'currency_symbol',
        'date_format',
        'time_format',
        'email_from_address',
        'email_from_name',
        'email_reply_to',
        'storage_limit',
        'storage_used_bytes',
        'data_retention_days',
        'auto_archive_old_data',
        'two_factor_required',
        'single_sign_on_enabled',
        'sso_provider',
        'sso_domain',
    ];

    protected $casts = [
        'custom_domain_verified' => 'boolean',
        'custom_domain_verified_at' => 'datetime',
        'analytics_enabled' => 'boolean',
        'analytics_anonymize_ip' => 'boolean',
        'feature_parent_portal' => 'boolean',
        'feature_sms_notifications' => 'boolean',
        'feature_mobile_app' => 'boolean',
        'feature_advanced_reports' => 'boolean',
        'feature_api_access' => 'boolean',
        'enabled_modules' => 'array',
        'auto_archive_old_data' => 'boolean',
        'two_factor_required' => 'boolean',
        'single_sign_on_enabled' => 'boolean',
        'data_retention_days' => 'integer',
        'deleted_at' => 'datetime',
    ];

    /**
     * School relationship
     */
    public function school()
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Check if feature is enabled
     */
    public function hasFeature(string $feature): bool
    {
        $field = 'feature_' . $feature;
        return $this->$field ?? false;
    }

    /**
     * Check if module is enabled
     */
    public function hasModule(string $module): bool
    {
        return in_array($module, $this->enabled_modules ?? []);
    }

    /**
     * Enable module
     */
    public function enableModule(string $module): void
    {
        $modules = $this->enabled_modules ?? [];
        if (!in_array($module, $modules)) {
            $modules[] = $module;
            $this->update(['enabled_modules' => $modules]);
        }
    }

    /**
     * Disable module
     */
    public function disableModule(string $module): void
    {
        $modules = array_diff($this->enabled_modules ?? [], [$module]);
        $this->update(['enabled_modules' => $modules]);
    }

    /**
     * Get available storage in bytes
     */
    public function getAvailableStorage(): int
    {
        $limits = [
            '5GB' => 5 * 1024 * 1024 * 1024,
            '10GB' => 10 * 1024 * 1024 * 1024,
            '50GB' => 50 * 1024 * 1024 * 1024,
            '100GB' => 100 * 1024 * 1024 * 1024,
            'unlimited' => PHP_INT_MAX,
        ];

        $limit = $limits[$this->storage_limit] ?? $limits['5GB'];
        return max(0, $limit - $this->storage_used_bytes);
    }

    /**
     * Check if storage limit exceeded
     */
    public function isStorageLimitExceeded(): bool
    {
        return $this->getAvailableStorage() <= 0;
    }

    /**
     * Increment storage used
     */
    public function incrementStorageUsed(int $bytes): void
    {
        $this->update(['storage_used_bytes' => $this->storage_used_bytes + $bytes]);
    }

    /**
     * Get analytics config for tenant
     */
    public function getAnalyticsConfig(): array
    {
        if (!$this->analytics_enabled) {
            return ['enabled' => false];
        }

        return [
            'enabled' => true,
            'provider' => $this->analytics_provider,
            'tracking_id' => $this->analytics_tracking_id,
            'anonymize_ip' => $this->analytics_anonymize_ip,
        ];
    }

    /**
     * Get branding config
     */
    public function getBrandingConfig(): array
    {
        return [
            'logo' => $this->logo_path ? asset('storage/' . $this->logo_path) : null,
            'favicon' => $this->favicon_path ? asset('storage/' . $this->favicon_path) : null,
            'primary_color' => $this->primary_color,
            'secondary_color' => $this->secondary_color,
            'accent_color' => $this->accent_color,
            'school_name' => $this->school_name_display ?? $this->school->name,
            'footer_text' => $this->footer_text,
        ];
    }

    /**
     * Get localization config
     */
    public function getLocalizationConfig(): array
    {
        return [
            'timezone' => $this->timezone,
            'currency' => $this->currency,
            'currency_symbol' => $this->currency_symbol,
            'date_format' => $this->date_format,
            'time_format' => $this->time_format,
        ];
    }

    /**
     * Verify custom domain
     */
    public function verifyCustomDomain(): bool
    {
        if (!$this->custom_domain) {
            return false;
        }

        // Check DNS records, SSL certificate, etc.
        // This is a simplified version
        $this->update([
            'custom_domain_verified' => true,
            'custom_domain_verified_at' => now(),
        ]);

        return true;
    }
}
