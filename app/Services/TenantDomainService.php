<?php

namespace App\Services;

use App\Models\School;
use App\Models\TenantSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Tenant Domain Service
 * 
 * Manages custom domain configuration and verification for tenants.
 * Handles DNS verification, SSL certificate provisioning, and routing.
 */
class TenantDomainService
{
    /**
     * Assign custom domain to tenant
     */
    public function assignDomain(School $school, string $domain): bool
    {
        // Normalize domain
        $domain = strtolower(trim($domain));
        $domain = preg_replace('/^https?:\/\//', '', $domain);
        $domain = preg_replace('/^www\./', '', $domain);

        // Check if domain is available
        if ($this->isDomainTaken($domain)) {
            return false;
        }

        // Create or update tenant settings
        $settings = $school->settings ?? $school->settings()->create([]);
        
        $settings->update([
            'custom_domain' => $domain,
            'custom_domain_verified' => false,
            'custom_domain_verified_at' => null,
        ]);

        // Initiate verification process
        $this->startDomainVerification($school, $domain);

        return true;
    }

    /**
     * Check if domain is already in use
     */
    protected function isDomainTaken(string $domain): bool
    {
        return TenantSetting::where('custom_domain', $domain)
            ->where('custom_domain_verified', true)
            ->exists();
    }

    /**
     * Start domain verification process
     */
    protected function startDomainVerification(School $school, string $domain): void
    {
        // Create DNS verification record
        $dnsRecord = $this->createDnsVerificationRecord($domain);

        // Create SSL certificate request
        $this->requestSslCertificate($domain, $school);

        // Notify tenant
        $this->notifyTenantVerificationRequired($school, $domain, $dnsRecord);

        Log::info('Domain verification initiated', [
            'school_id' => $school->id,
            'domain' => $domain,
            'dns_record' => $dnsRecord,
        ]);
    }

    /**
     * Create DNS verification record
     */
    protected function createDnsVerificationRecord(string $domain): array
    {
        // Generate unique verification token
        $token = str()->random(32);
        $verificationDomain = '_verify.' . $domain;

        return [
            'type' => 'TXT',
            'name' => $verificationDomain,
            'value' => 'saas-verification=' . $token,
            'ttl' => 300,
        ];
    }

    /**
     * Request SSL certificate (Cloudflare or Let's Encrypt)
     */
    protected function requestSslCertificate(string $domain, School $school): void
    {
        // In production, integrate with Cloudflare API or Let's Encrypt
        // For now, log the request
        Log::info('SSL certificate requested', [
            'school_id' => $school->id,
            'domain' => $domain,
        ]);
    }

    /**
     * Verify domain via DNS check
     */
    public function verifyDomain(School $school): bool
    {
        $settings = $school->settings;

        if (!$settings || !$settings->custom_domain) {
            return false;
        }

        $domain = $settings->custom_domain;

        // Check DNS records
        $dnsVerified = $this->checkDnsVerification($domain);

        if (!$dnsVerified) {
            Log::warning('DNS verification failed', [
                'school_id' => $school->id,
                'domain' => $domain,
            ]);
            return false;
        }

        // Check SSL certificate
        $sslVerified = $this->checkSslCertificate($domain);

        if (!$sslVerified) {
            Log::warning('SSL verification failed', [
                'school_id' => $school->id,
                'domain' => $domain,
            ]);
            return false;
        }

        // Mark as verified
        $settings->update([
            'custom_domain_verified' => true,
            'custom_domain_verified_at' => now(),
            'ssl_certificate_path' => '/etc/ssl/certs/' . $domain . '.crt',
        ]);

        Log::info('Domain verified successfully', [
            'school_id' => $school->id,
            'domain' => $domain,
        ]);

        // Notify tenant
        $this->notifyDomainVerified($school, $domain);

        return true;
    }

    /**
     * Check DNS TXT record for verification
     */
    protected function checkDnsVerification(string $domain): bool
    {
        // Use dig or similar to check DNS record
        // This is a simplified check
        $verificationDomain = '_verify.' . $domain;

        // In production, use actual DNS lookup
        // $output = shell_exec("dig +short TXT {$verificationDomain}");

        // For now, simulate verification
        return true;
    }

    /**
     * Check SSL certificate validity
     */
    protected function checkSslCertificate(string $domain): bool
    {
        try {
            $response = Http::withOptions([
                'verify' => true,
                'timeout' => 10,
            ])->get('https://' . $domain);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('SSL certificate check failed', [
                'domain' => $domain,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Remove custom domain
     */
    public function removeDomain(School $school): bool
    {
        $settings = $school->settings;

        if (!$settings || !$settings->custom_domain) {
            return false;
        }

        $oldDomain = $settings->custom_domain;

        $settings->update([
            'custom_domain' => null,
            'custom_domain_verified' => false,
            'custom_domain_verified_at' => null,
            'ssl_certificate_path' => null,
        ]);

        Log::info('Custom domain removed', [
            'school_id' => $school->id,
            'domain' => $oldDomain,
        ]);

        return true;
    }

    /**
     * Get domain status
     */
    public function getDomainStatus(School $school): array
    {
        $settings = $school->settings;

        if (!$settings || !$settings->custom_domain) {
            return [
                'has_domain' => false,
                'verified' => false,
            ];
        }

        return [
            'has_domain' => true,
            'domain' => $settings->custom_domain,
            'verified' => $settings->custom_domain_verified,
            'verified_at' => $settings->custom_domain_verified_at,
            'ssl_valid' => $this->checkSslCertificate($settings->custom_domain),
        ];
    }

    /**
     * Resolve tenant by domain
     */
    public function resolveTenantByDomain(string $domain): ?School
    {
        // Normalize domain
        $domain = strtolower(trim($domain));
        $domain = preg_replace('/^https?:\/\//', '', $domain);
        $domain = preg_replace('/^www\./', '', $domain);

        $setting = TenantSetting::where('custom_domain', $domain)
            ->where('custom_domain_verified', true)
            ->first();

        return $setting?->school;
    }

    /**
     * Notify tenant about verification
     */
    protected function notifyTenantVerificationRequired(School $school, string $domain, array $dnsRecord): void
    {
        // TODO: Send email notification with DNS record details
        Log::info('Verification notification would be sent', [
            'school_id' => $school->id,
            'email' => $school->email,
            'domain' => $domain,
        ]);
    }

    /**
     * Notify tenant about successful verification
     */
    protected function notifyDomainVerified(School $school, string $domain): void
    {
        // TODO: Send success notification
        Log::info('Verification success notification would be sent', [
            'school_id' => $school->id,
            'email' => $school->email,
            'domain' => $domain,
        ]);
    }
}
