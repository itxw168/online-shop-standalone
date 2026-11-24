ARG PHP_IMAGE=php:8.3-fpm-alpine
FROM ${PHP_IMAGE}

ENV TZ=Asia/Shanghai

# 官方 php:8.3-fpm-alpine 已内置 mbstring / pdo_sqlite / openssl。
# 拆成多个小层，避免单层过大导致网络上传中断。
# 国内网络下 dl-cdn 常被阻断，构建时改用阿里云镜像源
RUN sed -i 's#https://dl-cdn.alpinelinux.org#https://mirrors.aliyun.com#g' /etc/apk/repositories
RUN apk add --no-cache nginx
RUN apk add --no-cache supervisor
RUN apk add --no-cache curl tzdata
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime \
    && echo $TZ > /etc/timezone \
    && mkdir -p /run/nginx /var/log/nginx

COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-shop.ini
COPY docker/entrypoint.sh /usr/local/bin/shop-entrypoint.sh
RUN chmod 0755 /usr/local/bin/shop-entrypoint.sh

WORKDIR /var/www/html

# 运行数据与密钥不进入镜像；首次启动由 entrypoint 生成。
COPY . /var/www/html
RUN mkdir -p /var/www/html/data /var/www/html/secret \
    && chown -R www-data:www-data /var/www/html

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl -fsS http://127.0.0.1/ >/dev/null || exit 1

ENTRYPOINT ["/usr/local/bin/shop-entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
