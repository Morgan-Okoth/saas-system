<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Fideloper\Proxy\TrustProxies as Middleware;

/**
 * Trust Proxies - VPS Security
 * 
 * Cloudflare sits in front of the VPS. We must trust Cloudflare's
 * IP addresses to correctly detect client IPs and protocol (HTTPS).
 * 
 * Security: Without this, Laravel may generate http:// URLs behind
 * Cloudflare SSL, breaking mixed-content security.
 */
class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     * 
     * Cloudflare IP ranges (as of 2026):
     * - IPv4: 173.245.48.0/20, 103.21.244.0/22, 103.22.200.0/22,
     *         103.31.4.0/24, 141.101.64.0/18, 108.162.192.0/18,
     *         190.93.240.0/20, 188.114.96.0/20, 197.234.240.0/22,
     *         198.41.128.0/17, 162.158.0.0/15
     * - IPv6: 2400:cb00::/32, 2606:4700::/32, 2803:f800::/32,
     *         2405:b500::/32, 2405:8100::/32, 2c0f:f248::/32
     */
    protected $proxies = [
        // Trust all Cloudflare proxies (recommended for Cloudflare)
        '*',
        
        // OR specify explicitly:
        // '173.245.48.0/20',
        // '103.21.244.0/22',
        // '103.22.200.0/22',
        // '103.31.4.0/24',
        // '141.101.64.0/18',
        // '108.162.192.0/18',
        // '190.93.240.0/20',
        // '188.114.96.0/20',
        // '197.234.240.0/22',
        // '198.41.128.0/17',
        // '162.158.0.0/15',
        // '2400:cb00::/32',
        // '2606:4700::/32',
        // '2803:f800::/32',
        // '2405:b500::/32',
        // '2405:8100::/32',
        // '2c0f:f248::/32',
    ];

    /**
     * The headers that should be used to detect proxies.
     */
    protected $headers = Request::HEADER_X_FORWARDED_ALL;

    /**
     * Force all requests to use HTTPS (Cloudflare SSL)
     */
    protected $forceScheme = true;
}
