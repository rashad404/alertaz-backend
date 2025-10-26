#!/bin/bash
cd /Users/macmini/projects/alertaz/backend
for i in 0 10 20 30 40 50; do
  /opt/homebrew/bin/php artisan schedule:run >> /dev/null 2>&1 &
  sleep 10
done
