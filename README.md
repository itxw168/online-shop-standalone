# 在线商城 · 独立服务站

**一套零依赖、单目录部署的自助下单商城（PHP + SQLite）**

> ✅ **独立部署** — 不依赖主站、不需要 MySQL、不需要 Composer / Node
> ✅ **Docker 化** — 一条 `docker compose up -d --build` 即可运行
> ✅ **飞牛 fnOS** — 提供 Docker 应用包（`.fpk`）骨架，可安装到 fnOS 应用中心
> ✅ **可二次开发** — 原生 PHP + SQLite + MIT 协议

![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-blue)
![Docker](https://img.shields.io/badge/Docker-ready-2496ED)
![fnOS](https://img.shields.io/badge/fnOS-fpk-00A4FF)
![License MIT](https://img.shields.io/badge/License-MIT-green)

> **演示数据说明**：仓库自带的是虚构演示商品与占位联系方式，不含任何真实订单、客户资料或收款码。正式上线前请在后台替换为你的商品与收款信息。
>
> 工作区里的 `secret/config.secret.php` 是演示密钥；正式部署时请删除它，让 Docker 首次启动重新生成。`data/shop.json` 当前是演示明文，后台保存一次配置后会自动加密。

---

## 📖 项目介绍

在线商城 · 独立服务站是一套面向个人服务者与小微团队的「自助下单 + 订单管理」系统：

**客户选服务 → 下单 → 扫码付款 → 商家核对到账 → 交付**

客户无需注册即可下单、查询进度；商家只需要一个 PHP 环境或一台装了 Docker 的 NAS，就能把整套商城跑起来。

系统采用单目录结构、零外部依赖设计：不依赖框架，不需要 Composer / Node，不需要 MySQL，订单存本地 SQLite 文件，商品配置使用 AES-256 加密 JSON。

### 适用场景

- 电脑技术服务 / 远程维修接单
- 虚拟商品、软件资源、素材包销售
- 预约类服务（到店、上门、指定时间交付）
- 培训咨询、工作室课程报名与收款
- 任何「先收款后交付」的小微生意

### 三种部署方式

| 方式 | 适合谁 | 说明 |
|---|---|---|
| **Docker Compose**（推荐） | NAS、VPS、有 Docker 的服务器 | 镜像内置 PHP 8.3 + Nginx（php-fpm），数据挂载到本地目录 |
| **传统 PHP 部署** | 虚拟主机、已有 PHP 站点 | 上传目录即可，支持 PHP 7.4+ |
| **飞牛 fnOS 应用** | 飞牛 NAS 用户 | 通过 `.fpk` 安装，Docker 容器运行，数据保存在应用数据目录 |

---

## 📥 下载

| 内容 | 下载 | 说明 |
|---|---|---|
| **源码包 v1.0.3** | [online-shop-1.0.3-source.zip](https://github.com/itxw168/online-shop-standalone/releases/download/v1.0.3/online-shop-1.0.3-source.zip) | 完整源码；Docker 首次启动会自动生成 `data/` 与 `secret/` |
| **飞牛 fnOS 安装包 v1.0.3** | [online-shop-1.0.3.fpk](https://github.com/itxw168/online-shop-standalone/releases/download/v1.0.3/online-shop-1.0.3.fpk) | 在 fnOS 应用中心「手动安装」 |
| **Docker 镜像** | [itxw888/online-shop](https://hub.docker.com/r/itxw888/online-shop) | `docker pull itxw888/online-shop:1.0.3` |
| **Docker 部署教程** | [docs/Docker部署教程.md](docs/Docker部署教程.md) | Compose 配置、反向代理、升级与备份 |

---

## ✨ 功能特性

### 前台（客户侧）

- 🛍 **分类 + 商品展示**：分类卡片、商品列表、价格、标签、图标，支持搜索
- 🧾 **多选下单**：一次勾选多件商品合并下单，单商品也可直接下单
- 📝 **下单表单可配置**：微信 / 电话 / 预约时间 / 备注，必填项后台开关控制
- 💰 **收款码收款**：聚合码（一码收款）或微信 / 支付宝分开收款
- 🔖 **自动备注码**：每单生成唯一备注码，支持客户自助更换
- 🔍 **订单查询**：客户凭手机号 / 微信号自助查询，无需注册
- 🌙 **深色主题 + 响应式**：手机 / 平板 / 电脑都可用

### 后台（管理侧）

- 📊 **订单管理**：状态流转、订单详情、CSV 导出
- 📦 **商品 / 分类管理**：排序、图标、标签、库存、价格、上下架
- 💳 **收款与表单**：收款码、收款人名称、付款提示语、表单必填项
- 🔔 **消息推送**：Gotify / ntfy / 邮件 SMTP / 通用 Webhook 四通道，带失败重试队列
- 🛡 **风控与清理**：IP 限流、蜜罐、同 IP 未付款订单上限、超时失效
- 📋 **操作审计日志**：订单变更 / 登录 / 配置修改留痕
- 📖 **内置使用说明**：后台自带「三步跑起来」向导
- 🎨 **明暗主题一键切换**

### 安全设计

- 🔐 商品配置 AES-256 加密存储，密钥独立目录保管
- 🚦 接口限流 + 同源校验 + 蜜罐字段
- 🧱 自带 Web 防护规则：`data/`、`secret/`、`inc/`、`cron/` 禁止直接访问
- 👤 后台账号 + CSRF 双重校验；默认密码未修改时持续提醒
- 📦 订单数据为本地 SQLite 文件，数据完全自己掌控

---

## 🐳 Docker 部署（推荐）

> 完整步骤（预构建镜像、Compose 配置、反向代理、升级、备份与排错）见 **[Docker 部署教程](docs/Docker部署教程.md)**。

### 1. 准备

```bash
cp .env.example .env
# 按需修改端口与初始账号
```

`.env` 主要配置：

| 变量 | 默认值 | 说明 |
|---|---|---|
| `SHOP_PORT` | `8080` | 宿主机访问端口 |
| `SHOP_IMAGE` | `online-shop:local` | 镜像名；发布后可改为仓库地址 |
| `PHP_IMAGE` | `php:8.3-fpm-alpine` | 构建用基础镜像；国内拉取慢时可改成镜像加速地址 |
| `SHOP_ADMIN_USER` | `admin` | 首次启动创建的后台账号 |
| `SHOP_ADMIN_PASSWORD` | `admin123` | 首次启动创建的密码；之后修改不会覆盖已有账号 |

### 2. 启动

```bash
docker compose up -d --build
```

- 前台：`http://服务器IP:8080/`
- 后台：`http://服务器IP:8080/admin.php`
- 默认账号：`admin` / `admin123`（首次登录后请立即修改）

### 3. 数据与备份

| 路径 | 内容 |
|---|---|
| `./data/` | `shop.json` 配置、`shop.db` 订单库、`account.php` 后台账号 |
| `./secret/` | `config.secret.php` AES 密钥 |

备份这两个目录即可。容器重启不会重置数据；`data/` 与 `secret/` 不进入镜像。

### 4. 发布镜像（上架/多机部署时）

```bash
docker build --build-arg PHP_IMAGE="${PHP_IMAGE:-php:8.3-fpm-alpine}" \
  -t itxw888/online-shop:1.0.3 .
docker push itxw888/online-shop:1.0.3
```

当前已发布 `itxw888/online-shop:1.0.3`（Docker Hub，amd64）。在 `.env` 中设置 `SHOP_IMAGE=itxw888/online-shop:1.0.3`，或修改 `fnos/app/docker/docker-compose.yaml` 中的镜像地址。

> 已推送的 Docker Hub 仓库如果是私有的，飞牛安装时无法匿名拉取；请把仓库可见性设为 Public，或改用可匿名拉的仓库。

> 建议使用阿里云 ACR、腾讯云 TCR、华为云 SWR 或 GHCR 等可匿名/可登录拉取的仓库，并固定版本 tag，不要用 `latest`。

---

## 🐘 传统 PHP 部署

1. **上传**：把项目目录上传到网站根目录（或子目录）
2. **权限**：`data/` 可写；`secret/` 建议仅 Web 用户可读
3. **访问保护**：
   - Apache：保持 `.htaccess`，并开启 `AllowOverride`
   - Nginx：按 [部署说明.md](部署说明.md) 添加 `data/`、`secret/`、`inc/`、`cron/` 的 deny 规则
4. **登录后台**：访问 `admin.php`，默认 `admin` / `admin123`，登录后立即修改
5. **配置商店**：收款码 → 商品 → 推送（可选）

计划任务（可选）：每 5 分钟执行一次推送失败重试。

```bash
php /path/to/cron/push_retry.php
```

---

## 🧩 飞牛 fnOS 应用

`fnos/` 是可直接用 `fnpack` 打包的 Docker 应用工程：

```bash
cd fnos
./build.sh
# 生成 online-shop-1.0.3.fpk（具体文件名以 fnpack 输出为准）
```

安装前注意：

1. 先把镜像构建并推送到 NAS 可访问的仓库；
2. 修改 `fnos/app/docker/docker-compose.yaml` 的 `image`，或设置构建环境变量；
3. 在 fnOS 应用中心「手动安装」选择 `.fpk`，端口默认 `8080`；
4. 数据保存在 `/var/apps/online-shop/var/`，升级/卸载默认保留。

详见 [fnos/README.md](fnos/README.md)。

---

## 📂 目录结构

```text
在线商城独立服务站/
├── index.php                前台首页
├── query.php                订单查询
├── contact.php              联系方式
├── api.php                  下单 / 查询 / 标记付款接口
├── admin.php                后台入口与路由
├── inc/                     核心逻辑与后台视图
│   ├── shop_store.php       数据层（配置 / 订单 / 风控）
│   ├── admin_view.php       后台页面
│   ├── push.php             推送通道
│   ├── crypt.php            AES 加密
│   ├── ip_geo.php           IP 归属地
│   ├── contact_view.php     联系方式页
│   └── guide.php            后台使用说明
├── cron/                    推送重试 / IP 库 / 环境自检
├── data/                    ★ 运行数据（不提交、不进入镜像）
├── secret/                  ★ AES 密钥（不提交、不进入镜像）
├── docker/                  Docker 构建与初始化文件
├── fnos/                    飞牛 fnOS 应用包工程
├── assets/                  演示占位图
├── docs/                    部署教程与技术文档
├── Dockerfile
├── docker-compose.yml
└── 部署说明.md
```

---

## ❓ 常见问题

- **前台提示「下单功能正在维护中」** → 缺少 `pdo_sqlite` 扩展；Docker 镜像已内置
- **收不到推送** → 在「消息推送 → 测试推送」逐个通道验证
- **忘记后台密码** → 删除 `data/account.php` 后重启容器（或重新访问后台），会按 `.env` 重新生成初始账号；本地部署会生成 `admin/admin123`
- **Docker 镜像拉取失败** → 检查 NAS 能否访问镜像仓库；国内环境建议使用国内 registry
- **端口被占用** → 修改 `.env` 的 `SHOP_PORT`，同时同步 `fnos/app/ui/config` 的 `port`
- **如何备份** → 备份 `data/` 与 `secret/` 两个目录

---

## ⚠️ 安全提醒

1. 默认账号 `admin/admin123` 只用于首次登录，**上线前必须修改**
2. `secret/` 是配置加密密钥：**部署后不可更换**，否则历史加密配置无法解密；备份时与 `data/` 一起备份
3. 生产环境请配置 HTTPS；公开商城不要把后台直接暴露在公网
4. 仓库自带演示数据，正式使用前请在后台替换商品、收款码与联系方式
5. Nginx 用户必须手动配置 `data/`、`secret/`、`inc/`、`cron/` 的拒绝规则

---

## 📜 开源协议

[MIT License](LICENSE)

欢迎 Issue / PR 反馈，也欢迎基于本项目做二次开发。
