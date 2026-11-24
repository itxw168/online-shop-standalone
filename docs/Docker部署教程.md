# Docker 部署教程

本教程适用于 VPS、NAS、软路由或任何安装了 Docker 的 Linux / Windows / macOS 机器。
商城使用 PHP 8.3 + Nginx（php-fpm）+ SQLite，数据全部保存在本地目录，不依赖 MySQL。

---

## 1. 前置条件

- Docker Engine 20.10+（Docker Desktop 也可以）
- Docker Compose v2（命令是 `docker compose`，不是旧的 `docker-compose`）
- 一个可访问的端口，默认 `8080`
- 建议 1GB 以上内存、1GB 以上磁盘空间

检查版本：

```bash
docker --version
docker compose version
```

---

## 2. 获取源码

任选一种：

```bash
git clone https://github.com/itxw168/online-shop-standalone.git
cd online-shop-standalone
```

或从 [Releases](https://github.com/itxw168/online-shop-standalone/releases) 下载源码包并解压。

---

## 3. 配置 `.env`

```bash
cp .env.example .env
```

编辑 `.env`：

```dotenv
# 宿主机访问端口
SHOP_PORT=8080

# 构建用基础镜像；国内拉取慢时改成镜像加速地址
PHP_IMAGE=php:8.3-fpm-alpine

# 使用已发布镜像时改成下面这行；从源码构建则保留 online-shop:local
SHOP_IMAGE=itxw888/online-shop:1.0.3

# 首次启动创建的后台账号；已有 data/account.php 时不会覆盖
SHOP_ADMIN_USER=admin
SHOP_ADMIN_PASSWORD=admin123
```

> `SHOP_ADMIN_PASSWORD` 只在第一次启动、`data/account.php` 还不存在时生效。
> 上线前请把 `admin123` 改成强密码；登录后台后也可以再修改。

---

## 4. 启动

### 方式 A：使用已发布镜像（推荐）

```bash
docker compose pull
docker compose up -d --no-build
```

### 方式 B：从源码构建

```bash
docker compose up -d --build
```

### 方式 C：不使用 Compose，直接 `docker run`

```bash
docker run -d \
  --name online-shop \
  --restart unless-stopped \
  -p 8080:80 \
  -e TZ=Asia/Shanghai \
  -e SHOP_ADMIN_USER=admin \
  -e SHOP_ADMIN_PASSWORD=admin123 \
  -v "$PWD/data:/var/www/html/data" \
  -v "$PWD/secret:/var/www/html/secret" \
  itxw888/online-shop:1.0.3
```

---

## 5. 访问

- 前台：`http://服务器IP:8080/`
- 后台：`http://服务器IP:8080/admin.php`
- 默认账号：`admin` / `admin123`

首次启动日志应包含：

```text
shop-init generated secret/config.secret.php
seeded data/shop.json with demo data
created admin account: admin
```

查看日志：

```bash
docker compose logs -f shop
```

健康状态：

```bash
docker compose ps
```

---

## 6. Compose 文件说明

完整文件在仓库根目录：[docker-compose.yml](../docker-compose.yml)。

核心结构：

```yaml
name: online-shop

services:
  shop:
    build: .
    image: ${SHOP_IMAGE:-online-shop:local}
    container_name: online-shop
    restart: unless-stopped
    ports:
      - "${SHOP_PORT:-8080}:80"
    environment:
      TZ: Asia/Shanghai
      SHOP_ADMIN_USER: ${SHOP_ADMIN_USER:-admin}
      SHOP_ADMIN_PASSWORD: ${SHOP_ADMIN_PASSWORD:-admin123}
    volumes:
      - ./data:/var/www/html/data
      - ./secret:/var/www/html/secret
```

| 配置 | 作用 |
|---|---|
| `ports` | 宿主机端口映射到容器 80；只改左边 |
| `volumes` | `data/` 存配置、订单库、后台账号；`secret/` 存 AES 密钥 |
| `environment` | 首次启动创建管理员账号；`TZ` 固定时区 |
| `image` | 已发布镜像地址；从源码构建时可用本地 tag |
| `build` | 源码构建配置；只拉镜像时用 `--no-build` |

> `data/` 和 `secret/` 必须持久化。删掉它们等于清空商城和密钥。

---

## 7. 数据、备份与恢复

| 路径 | 内容 |
|---|---|
| `data/shop.json` | 商品、页面文案、支付与推送配置（AES 加密） |
| `data/shop.db` | 订单、推送队列、审计日志（SQLite） |
| `data/account.php` | 后台账号与密码哈希 |
| `secret/config.secret.php` | AES-256 密钥 |

备份：

```bash
tar czf online-shop-backup-$(date +%F).tar.gz data secret
```

恢复：

```bash
docker compose down
tar xzf online-shop-backup-2026-01-01.tar.gz
docker compose up -d --no-build
```

注意：

- `secret/` 丢失后，已加密的 `data/shop.json` 无法解密；备份时必须一起备份。
- 不要把 `data/`、`secret/` 提交到 Git，也不要放进公开镜像。

---

## 8. 反向代理与 HTTPS

生产环境建议只暴露 80/443，把 `8080` 收到防火墙后面。

### Nginx

```nginx
server {
    listen 80;
    server_name shop.example.com;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

### Caddy

```caddyfile
shop.example.com {
    reverse_proxy 127.0.0.1:8080
}
```

说明：

- 不需要 WebSocket。
- 反向代理要传 `X-Forwarded-Proto`，后台才能正确判断 HTTPS。
- 容器内 Nginx 已经拒绝访问 `data/`、`secret/`、`inc/`、`cron/`；反向代理不要额外暴露这些目录。
- 公开商城建议开启 HTTPS；后台不要直接裸露在公网，至少加 IP 白名单或 Basic Auth。

---

## 9. 升级

```bash
# 1. 先备份
tar czf online-shop-backup-$(date +%F).tar.gz data secret

# 2. 修改 .env 里的 SHOP_IMAGE 到新版本
#    SHOP_IMAGE=itxw888/online-shop:1.0.4

# 3. 拉取并重建容器
docker compose pull
docker compose up -d --no-build
```

从源码升级：

```bash
git pull
docker compose up -d --build
```

升级不会删除 `data/` 与 `secret/`；如果版本说明要求迁移，按 Release Notes 操作。

---

## 10. 停止与卸载

停止：

```bash
docker compose down
```

停止并删除容器（保留数据）：

```bash
docker compose down
```

彻底删除（**会丢数据，先备份**）：

```bash
docker compose down
rm -rf data secret
```

---

## 11. 飞牛 fnOS

fnOS 用户可以直接安装 `.fpk`：

1. 下载 [online-shop-1.0.3.fpk](https://github.com/itxw168/online-shop-standalone/releases/download/v1.0.3/online-shop-1.0.3.fpk)
2. 打开 fnOS「应用中心 → 手动安装」，选择 `.fpk`
3. 安装后从桌面「在线商城 / 商城后台」进入
4. 数据保存在 `/var/apps/online-shop/var/`

`.fpk` 内部仍然使用 Docker 镜像 `itxw888/online-shop:1.0.3`，所以 NAS 需要能拉取该镜像。

---

## 12. 常见问题

| 问题 | 处理 |
|---|---|
| `docker compose` 命令不存在 | 安装 Compose v2；旧版请用 `docker-compose` 并自行转换 |
| 端口被占用 | 改 `.env` 的 `SHOP_PORT`，再 `docker compose up -d` |
| 拉取镜像失败 | 检查 NAS/VPS 到 Docker Hub 的网络；国内可配置 registry mirror |
| 前台提示「下单功能正在维护中」 | 镜像已内置 `pdo_sqlite`；自建镜像请确认扩展 |
| 后台登录失败 | 确认 `data/account.php` 是否存在；删掉它并重启会按 `.env` 重新生成 |
| 页面 403 | 不要直接访问 `data/`、`secret/`、`inc/`、`cron/`；这些目录本身禁止访问 |
| 时区不对 | 确认 `TZ=Asia/Shanghai`，并重启容器 |
| 推送失败重试不执行 | 容器内已每 5 分钟运行 `cron/push_retry.php`；也可手动 `docker compose exec shop php cron/push_retry.php` |
| ARM 设备无法运行 | 当前发布镜像为 `linux/amd64`；ARM 需自行 `docker build` 或使用多架构镜像 |

---

## 13. 安全清单

- [ ] 修改默认后台密码 `admin123`
- [ ] 备份 `data/` 与 `secret/`
- [ ] 配置 HTTPS
- [ ] 不要把 `8080` 直接暴露到公网
- [ ] 不要把 `data/`、`secret/` 提交到 Git
- [ ] 定期更新镜像版本，不使用 `latest`
- [ ] 后台路径建议加 IP 白名单或额外认证
