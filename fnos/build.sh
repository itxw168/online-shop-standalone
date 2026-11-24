#!/bin/bash
set -euo pipefail

cd "$(dirname "$0")"

if ! command -v fnpack >/dev/null 2>&1; then
    echo "未找到 fnpack。请先下载并加入 PATH：" >&2
    echo "  https://developer.fnnas.com/docs/cli/fnpack/" >&2
    exit 1
fi

for f in manifest config/privilege config/resource cmd/main app/docker/docker-compose.yaml app/ui/config ICON.PNG ICON_256.PNG; do
    if [ ! -f "$f" ]; then
        echo "缺少文件：$f" >&2
        exit 1
    fi
done

chmod +x cmd/main

version="$(awk -F= '$1=="version"{print $2; exit}' manifest)"
echo "构建 online-shop ${version} ..."
if [ -n "${FNOS_KEY:-}" ] && [ -n "${FNOS_CERT:-}" ]; then
    fnpack build --key "$FNOS_KEY" --cert "$FNOS_CERT"
else
    echo "警告：未设置 FNOS_KEY / FNOS_CERT，生成本地调试用 unsigned 包。" >&2
    echo "上架应用中心前必须用开发者证书签名。" >&2
    fnpack build --nosign
fi
echo "完成。生成文件：$(ls -1 ./*.fpk 2>/dev/null | tail -n 1)"
