#!/bin/bash
pgrep -f "queue:work" > /dev/null || (cd /home/ugn/api.alert.az && /usr/local/bin/ea-php82 artisan queue:work --sleep=3 --tries=100 >> /home/ugn/api.alert.az/storage/logs/queue.log 2>&1 &)
