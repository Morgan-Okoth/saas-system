#!/bin/bash
#
# Supervisor Setup for SaaS School System
# Run this script to configure and start queue workers
#

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log_info() { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }

log_info "Setting up Supervisor for SaaS School System..."

# Check if supervisor is installed
if ! command -v supervisorctl &> /dev/null; then
    log_error "Supervisor is not installed"
    log_info "Installing supervisor..."
    apt-get update
    apt-get install -y supervisor
fi

# Create supervisor config directory if it doesn't exist
mkdir -p /etc/supervisor/conf.d

# Create worker config
cat > /etc/supervisor/conf.d/saas-worker.conf << 'EOF'
[program:saas-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/saas/artisan queue:work redis --sleep=3 --tries=3 --timeout=60 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=3
redirect_stderr=true
stdout_logfile=/var/www/saas/storage/logs/worker.log
stopwaitsecs=3600
priority=100

[program:saas-schedule]
process_name=%(program_name)s
command=php /var/www/saas/artisan schedule:run
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/saas/storage/logs/schedule.log
startsecs=10
priority=200
EOF

log_info "Supervisor configuration created"

# Reload supervisor
log_info "Reloading supervisor..."
supervisorctl reread
supervisorctl update

# Start workers
log_info "Starting queue workers..."
supervisorctl start saas-worker:*

# Start scheduler
log_info "Starting scheduler..."
supervisorctl start saas-schedule

# Check status
sleep 2
log_info "Worker status:"
supervisorctl status

log_info "${GREEN}Supervisor setup completed!${NC}"
log_info ""
log_info "Useful commands:"
log_info "  - Check status: supervisorctl status"
log_info "  - Restart workers: supervisorctl restart saas-worker:*"
log_info "  - Stop workers: supervisorctl stop saas-worker:*"
log_info "  - View logs: tail -f /var/www/saas/storage/logs/worker.log"
log_info "  - Clear queue: php artisan queue:flush"
log_info "  - Retry failed jobs: php artisan queue:retry-failed"
