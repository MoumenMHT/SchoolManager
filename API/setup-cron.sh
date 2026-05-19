#!/bin/bash

# ==============================================================
# SchoolHub - Laravel Scheduler Cron Setup
# Run this script ONCE on the Linux server after deployment.
# It automatically adds the cron job for bills:mark-late.
# Usage: bash setup-cron.sh
# ==============================================================

# Get the absolute path of the api directory (where this script lives)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

CRON_JOB="* * * * * cd $SCRIPT_DIR && php artisan schedule:run >> /dev/null 2>&1"

# Check if the cron job already exists
if crontab -l 2>/dev/null | grep -qF "php artisan schedule:run"; then
    echo "✅ Cron job already exists. Nothing to do."
    crontab -l | grep "schedule:run"
else
    # Add the cron job without overwriting existing ones
    (crontab -l 2>/dev/null; echo "$CRON_JOB") | crontab -
    echo "✅ Cron job added successfully!"
    echo ""
    echo "   $CRON_JOB"
    echo ""
    echo "The Laravel scheduler will now run every minute."
    echo "bills:mark-late will execute automatically every day at midnight."
fi
