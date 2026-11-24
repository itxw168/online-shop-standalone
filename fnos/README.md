# 在线商城 · 飞牛 fnOS 应用包

本目录是可直接用 `fnpack` 构建的 Docker 应用工程，安装后由 fnOS 应用中心管理容器生命周期。

## 结构

| 路径 | 作用 |
|---|---|
| `manifest` | 应用元数据（名称、版本、平台、端口、桌面入口） |
| `config/privilege` | 包运行身份（容器应用保持 package 最小权限） |
| `config/resource` | 声明 Docker Compose 项目目录 |
| `cmd/main` | 启动/停止/状态检查；`status` 用 `docker inspect` 判断容器 |
| `app/docker/docker-compose.yaml` | 容器编排、端口、数据卷 |
| `app/ui/config` | 桌面入口（前台 / 后台）与图标 |
| `ICON.PNG` / `ICON_256.PNG` | 应用中心图标（64×64 / 256×256） |

## 构建

1. 安装 [fnpack](https://developer.fnnas.com/docs/cli/fnpack/)，确保 `fnpack` 在 `PATH` 中。
2. 在 `fnos/` 目录执行：

```bash
chmod +x build.sh
./build.sh
```

产物为当前目录下的 `.fpk`。`build.sh` 会检查必需文件并给 `cmd/main` 加执行权限。

## 安装前必须做的一件事：准备镜像

`.fpk` 不包含镜像，安装时由 Docker 从仓库拉取。默认镜像地址为：

```yaml
image: ${SHOP_IMAGE:-itxw888/online-shop:1.0.3}
```

请先在你的仓库构建并推送镜像：

```bash
docker build -t itxw888/online-shop:1.0.3 .
docker push itxw888/online-shop:1.0.3
```

当前 `itxw888/online-shop:1.0.3` 已推送。Docker Hub 新仓库若为私有，fnOS 匿名拉取会失败，请在 Docker Hub 仓库 **Settings → Visibility** 设为 **Public**。

然后修改 `app/docker/docker-compose.yaml` 中的 `image`，再重新 `./build.sh`。当前 `manifest` 为 `platform=x86`，镜像只需 `linux/amd64`；如果要在 ARM 设备安装，需构建 `linux/arm64` 镜像，并把 `manifest` 改为 `platform=all`。

## 安装与访问

1. 打开 fnOS 应用中心 → 手动安装 → 选择 `.fpk`。
2. 安装完成后从桌面入口打开「在线商城」。
3. 默认端口 `8080`；前台 `http://NAS地址:8080/`，后台 `http://NAS地址:8080/admin.php`。
4. 默认账号 `admin` / `admin123`；首次登录后立即修改。

## 数据位置

| 路径 | 内容 |
|---|---|
| `/var/apps/online-shop/var/data/` | `shop.json`、`shop.db`、`account.php` |
| `/var/apps/online-shop/var/secret/` | `config.secret.php`（AES 密钥） |

升级和卸载默认保留 `var/`；重装前请自行备份。不要在容器运行中直接改这两个目录里的文件。

## 已知限制

- 必须能访问镜像仓库；离线安装需要自行把镜像导入 NAS 的 Docker。
- 应用中心入口使用独立端口，不走 fnOS 统一网关。公开商城需要域名/HTTPS 时，请在 NAS 反向代理或路由器上配置。
- 默认账号仅用于首次登录；应用内没有「安装向导」，首次登录后请在「账号设置」修改密码。
- 卸载后 `var/` 数据默认保留，但建议在应用中心卸载前先备份。

## 签名

`fnos/build.sh` 默认在未设置 `FNOS_KEY` / `FNOS_CERT` 时生成 unsigned 包，仅用于本地调试，**不能上架**。正式发布前先在飞牛开发者后台导入开发者证书，然后：

```bash
FNOS_KEY=/path/to/developer.key FNOS_CERT=/path/to/developer.crt ./build.sh
```

## 发布前确认

- `manifest` 的 `maintainer_url` 指向 `https://github.com/itxw168/`，`distributor_url` 指向 `https://www.itxw.cc/`；如账号或官网变化请同步修改。
- `app/docker/docker-compose.yaml` 默认指向 `itxw888/online-shop:1.0.3`；如果你的 Docker Hub 账号不同，请改这里。
- `manifest` 的 `version`、镜像 tag、`changelog` 三处版本号要一致。

## 上架前检查

- [ ] 镜像已推送到公共/可登录仓库，tag 固定且不可变
- [ ] `manifest` 的 `version` 与镜像 tag 一致
- [ ] `platform=x86` 时只需验证 x86；改为 `platform=all` 时，镜像必须同时支持 amd64 / arm64
- [ ] `.fpk` 已用开发者证书签名（不是 `--nosign`）
- [ ] 在对应架构真机安装一次，验证前台、后台、下单、查询、升级
- [ ] 卸载/重装后数据仍保留
- [ ] 默认密码已替换或强制修改
- [ ] 应用中心要求：最终 `.fpk`、图标、真实截图、测试说明

参考：

- [应用框架](https://developer.fnnas.com/docs/core-concepts/framework/)
- [Manifest](https://developer.fnnas.com/docs/core-concepts/manifest/)
- [Docker 应用案例](https://developer.fnnas.com/docs/examples/docker/)
- [统一网关](https://developer.fnnas.com/docs/core-concepts/gateway-registration/)
