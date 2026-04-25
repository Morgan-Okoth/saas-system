<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant Settings Table Migration
 * 
 * Stores advanced tenant-specific configurations:
 * - Custom domains
 * - White-label settings (logo, colors, favicon)
 * - Analytics preferences
 * - Feature flags
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            
            // Custom domain
            $table->string('custom_domain')->nullable()->unique();
            $table->boolean('custom_domain_verified')->default(false);
            $table->timestamp('custom_domain_verified_at')->nullable();
            $table->string('ssl_certificate_path')->nullable();
            
            // White-label branding
            $table->string('logo_path')->nullable();
            $table->string('favicon_path')->nullable();
            $table->string('primary_color')->default('#3B82F6'); // blue-500
            $table->string('secondary_color')->default('#1E40AF'); // blue-900
            $table->string('accent_color')->default('#F59E0B'); // amber-500
            $table->string('school_name_display')->nullable();
            $table->string('footer_text')->nullable();
            
            // Analytics
            $table->boolean('analytics_enabled')->default(false);
            $table->string('analytics_provider')->nullable(); // google, matomo, custom
            $table->string('analytics_tracking_id')->nullable();
            $table->boolean('analytics_anonymize_ip')->default(true);
            
            // Feature flags
            $table->boolean('feature_parent_portal')->default(true);
            $table->boolean('feature_sms_notifications')->default(true);
            $table->boolean('feature_mobile_app')->default(true);
            $table->boolean('feature_advanced_reports')->default(false);
            $table->boolean('feature_api_access')->default(false);
            $table->json('enabled_modules')->default(json_encode([
                'attendance',
                'grades',
                'students',
                'courses',
            ]));
            
            // Localization
            $table->string('timezone')->default('Africa/Nairobi');
            $table->string('currency')->default('KES');
            $table->string('currency_symbol')->default('KSh');
            $table->string('date_format')->default('d/m/Y');
            $table->string('time_format')->default('H:i');
            
            // Email settings (per-tenant)
            $table->string('email_from_address')->nullable();
            $table->string('email_from_name')->nullable();
            $table->string('email_reply_to')->nullable();
            
            // Storage
            $table->string('storage_limit')->default('5GB');
            $table->bigInteger('storage_used_bytes')->default(0);
            
            // Data retention
            $table->integer('data_retention_days')->default(365);
            $table->boolean('auto_archive_old_data')->default(true);
            
            // Security
            $table->boolean('two_factor_required')->default(false);
            $table->boolean('single_sign_on_enabled')->default(false);
            $table->string('sso_provider')->nullable();
            $table->string('sso_domain')->nullable();
            
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('custom_domain');
            $table->index('custom_domain_verified');
            $table->index(['school_id', 'custom_domain']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_settings');
    }
};
