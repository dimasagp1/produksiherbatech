# VPS Deploy

- Ubuntu 22.04, PHP 8.4, MySQL 8, Redis, Nginx, Supervisor
- Cron: `* * * * * cd /var/www/herbatech && php artisan schedule:run >> /dev/null 2>&1`
- Queues: `odoo_sync,mrp,reports` via supervisor.conf.example
- Env: set `ODOO_*`, `HRIS_*`, `FINANCE_*` in `.env`
