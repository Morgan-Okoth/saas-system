# VPS Deployment Guide

## Overview
Production deployment for multi-tenant SaaS system on Oracle Cloud Free Tier VPS (Ubuntu).

## Prerequisites
- Ubuntu 22.04/24.04 VPS (Oracle Cloud Free Tier)
- Domain name (configured in Cloudflare)
- Cloudflare account (DNS + WAF + SSL)
- GitHub repository access

## Architecture
```
Cloudflare (DNS, SSL, WAF)
    ↓
VPS (Ubuntu + Nginx + PHP-FPM)
    ├─ Laravel Application
    ├─ Redis (Cache + Queue)
    ├─ PostgreSQL (Supabase/Neon or local)
    └─ Supervisor (Queue workers)
```

## Step 1: Server Setup

### Update System
```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y curl wget git unzip
```

### Install PHP 8.2
```bash
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.2 php8.2-fpm php8.2-mysql php8.2-pgsql \
    php8.2-redis php8.2-curl php8.2-mbstring php8.2-xml \
    php8.2-zip php8.2-bcmath php8.2-gd php8.2-cli
```

### Install Composer
```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer --version
```

### Install Node.js & npm
```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
npm --version
```

### Install Redis
```bash
sudo apt install -y redis-server
sudo systemctl enable redis-server
sudo systemctl start redis-server
```

## Step 2: Nginx Configuration

### Install Nginx
```bash
sudo apt install -y nginx
```

### Create Nginx Site Config
```bash
sudo nano /etc/nginx/sites-available/saas
```

```nginx
server {
    listen 80;
    server_name saas.yourdomain.com;
    root /var/www/saas/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Static files caching
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

### Enable Site
```bash
sudo ln -s /etc/nginx/sites-available/saas /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

## Step 3: SSL with Cloudflare

### Cloudflare Configuration
1. Login to Cloudflare dashboard
2. Add DNS record: `saas.yourdomain.com` → VPS IP (A record)
3. Enable SSL/TLS → Full (strict)
4. Enable WAF rules
5. Configure Page Rules (optional):
   - `http://saas.yourdomain.com/*` → Always use HTTPS
   - Cache Level: Cache Everything (for static assets)

### Auto-SSL with Cloudflare Origin CA
```bash
# Install Cloudflare Origin CA
wget https://github.com/cloudflare/cloudflare-origin-ca/archive/master.zip
unzip master.zip

# Generate certificate (365 days)
./origin-ca-install -cert-file /etc/nginx/ssl/origin.crt \
    -key-file /etc/nginx/ssl/origin.key \
    -host saas.yourdomain.com \
    -hosts saas.yourdomain.com \
    -days 365
```

Update Nginx config for SSL:
```nginx
listen 443 ssl http2;
ssl_certificate /etc/nginx/ssl/origin.crt;
ssl_certificate_key /etc/nginx/ssl/origin.key;
```

## Step 4: Deploy Application

### Clone Repository
```bash
cd /var/www
sudo git clone https://github.com/Morgan-Okoth/saas-system.git saas
cd saas
sudo git checkout main
```

### Set Permissions
```bash
sudo chown -R www-data:www-data /var/www/saas
sudo chmod -R 775 /var/www/saas/storage
sudo chmod -R 775 /var/www/saas/bootstrap/cache
```

### Install Dependencies
```bash
cd /var/www/saas
composer install --optimize-autoloader --no-dev
npm install
npm run build
```

### Environment Setup
```bash
cp .env.example .env
sudo nano .env
```

```env
APP_NAME="SaaS School System"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://saas.yourdomain.com

# Database (PostgreSQL/Supabase/Neon)
DB_CONNECTION=pgsql
DB_HOST=db.supabase.co
DB_PORT=5432
DB_DATABASE=saas_production
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Cache (Redis)
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120

# Cloudinary
CLOUDINARY_URL=cloudinary://API_KEY:API_SECRET@CLOUD_NAME
CLOUDINARY_UPLOAD_PRESET=saas_uploads

# Mail (Resend for production)
MAIL_MAILER=resend
MAIL_HOST=smtp.resend.com
MAIL_PORT=465
MAIL_USERNAME=resend
MAIL_PASSWORD=resend_api_key
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=notifications@saas.yourdomain.com
MAIL_FROM_NAME="SaaS School System"

# Services
RESEND_KEY=re_live_...
STRIPE_KEY=pk_live_...
STRIPE_SECRET=sk_live_...
M-PESA_CONSUMER_KEY=...
M-PESA_CONSUMER_SECRET=...
M-PESA_PASSKEY=...

# Security (Cloudflare)
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=strict
SESSION_DOMAIN=.yourdomain.com
TRUSTED_PROXIES=*
```

## Step 5: Database Migration

```bash
cd /var/www/saas
php artisan migrate --force
php artisan db:seed --class=PermissionSeeder --force
```

## Step 6: Queue Workers (Supervisor)

### Install Supervisor
```bash
sudo apt install -y supervisor
```

### Configure Supervisor
```bash
sudo nano /etc/supervisor/conf.d/saas-worker.conf
```

```ini
[program:saas-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/saas/artisan queue:work redis --sleep=3 --tries=3 --timeout=60
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=3
redirect_stderr=true
stdout_logfile=/var/www/saas/storage/logs/worker.log
stopwaitsecs=3600
```

### Start Supervisor
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

## Step 7: Scheduled Tasks (Cron)

```bash
sudo crontab -u www-data -e
```

```cron
* * * * * cd /var/www/saas && php artisan schedule:run >> /dev/null 2>&1
```

## Step 8: Firewall Configuration

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw allow 22/tcp
sudo ufw enable
sudo ufw status
```

## Step 9: Redis Optimization

```bash
sudo nano /etc/redis/redis.conf
```

```conf
# Set maxmemory
maxmemory 256mb
maxmemory-policy allkeys-lru

# Persistence
save 900 1
save 300 10
save 60 10000

# Disable dangerous commands
rename-command FLUSHDB ""
rename-command FLUSHALL ""
```

```bash
sudo systemctl restart redis-server
```

## Step 10: Monitoring

### Install Laravel Telescope (optional)
```bash
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate
```

### Log Rotation
```bash
sudo nano /etc/logrotate.d/saas
```

```
/var/www/saas/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
    postrotate
        systemctl reload php8.2-fpm > /dev/null 2>/dev/null || true
    endscript
}
```

## Step 11: Health Checks

Create health check route:
```php
// routes/web.php
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now(),
        'redis' => Redis::ping(),
        'database' => DB::connection()->getDatabaseName(),
    ]);
});
```

## Step 12: Backup Strategy

### Database Backup (daily)
```bash
sudo nano /etc/cron.daily/saas-db-backup
```

```bash
#!/bin/bash
DATE=$(date +%Y%m%d)
pg_dump -U username dbname | gzip > /backups/saas-$DATE.sql.gz
find /backups -name "*.gz" -mtime +30 -delete
```

## Verification

### Test Deployment
```bash
# Check Laravel version
php artisan --version

# Check queue workers
sudo supervisorctl status

# Check Redis
redis-cli ping

# Check Nginx
nginx -t
```

### Access Application
```
https://saas.yourdomain.com
```

## Troubleshooting

### Queue workers not processing
```bash
sudo supervisorctl restart saas-worker:*
php artisan queue:restart
```

### Permission errors
```bash
sudo chown -R www-data:www-data /var/www/saas
sudo chmod -R 775 /var/www/saas/storage
sudo chmod -R 775 /var/www/saas/bootstrap/cache
```

### Redis connection refused
```bash
sudo systemctl status redis-server
sudo systemctl restart redis-server
```

### SSL certificate issues
```bash
sudo nginx -t
sudo systemctl restart nginx
```

## Security Checklist

- [x] Cloudflare WAF enabled
- [x] SSL/TLS configured (Full strict)
- [x] App debug disabled in production
- [x] Session cookies secure & HttpOnly
- [x] CSRF protection enabled
- [x] Rate limiting on login
- [x] Queue workers isolated per tenant
- [x] Database backups automated
- [x] Firewall configured (UFW)
- [x] Redis password protected
- [x] Dangerous Redis commands disabled
- [x] Log rotation configured
- [x] File permissions locked down
- [x] Trusted proxies configured
- [x] CORS restricted
- [x] Content Security Policy ready

## Performance Optimization

1. **OPcache**: Enabled by default in PHP 8.2
2. **Redis**: Cache and queue in same instance
3. **Nginx**: Static asset caching (1 year)
4. **Cloudflare**: CDN + minification + compression
5. **Database**: Connection pooling via PgBouncer (optional)
6. **Queue**: Multiple workers for parallel processing

## Scaling Considerations

### Vertical Scaling (Oracle Cloud Free Tier)
- Upgrade to paid plan (4 OCPU, 24GB RAM)
- Increase Redis memory
- Add swap space

### Horizontal Scaling
- Separate database (Neon serverless)
- Dedicated Redis instance
- Multiple queue workers
- Load balancer + multiple app servers

### Database Optimization
- Read replicas
- Connection pooling
- Query optimization
- Index monitoring

## Cost Estimation (Monthly)

- Oracle Cloud Free Tier: $0
- Cloudflare (Free): $0
- Resend (2,500 emails/mo): $0
- Supabase (Free tier): $0
- **Total**: $0 (within free tiers)

## Support

For deployment issues:
1. Check logs: `tail -f storage/logs/laravel.log`
2. Check queue: `sudo supervisorctl status`
3. Check Redis: `redis-cli monitor`
4. Check Nginx: `sudo tail -f /var/log/nginx/error.log`
