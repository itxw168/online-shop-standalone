#!/bin/sh
set -eu

# 首次启动生成密钥/账号/演示配置；已存在则原样保留
php /var/www/html/docker/init.php

# 让 Apache 运行用户可读写挂载卷
chown -R www-data:www-data /var/www/html/data /var/www/html/secret
chmod 0750 /var/www/html/data /var/www/html/secret

# 推送失败重试：不依赖外部 cron，容器内每 5 分钟跑一次
(
    while true; do
        sleep 300
        su -s /bin/sh www-data -c 'php /var/www/html/cron/push_retry.php' >/dev/null 2>&1 || true
    done
) &

exec "$@"
