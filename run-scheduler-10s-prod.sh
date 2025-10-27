#!/bin/bash
# Production scheduler runner - runs Laravel scheduler every 10 seconds
# This enables sub-minute scheduled tasks (like everyThirtySeconds)

cd /home/ugn/api.alert.az || exit 1

for i in 0 10 20 30 40 50; do
  /usr/local/bin/ea-php82 artisan schedule:run >> /dev/null 2>&1 &
  sleep 10
done
