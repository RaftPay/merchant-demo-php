#!/usr/bin/env bash
set -euo pipefail

# ─── 颜色 ───
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

ok()   { echo -e "${GREEN}✔ $1${NC}"; }
warn() { echo -e "${YELLOW}⚠ $1${NC}"; }
fail() { echo -e "${RED}✘ $1${NC}"; exit 1; }

echo "=============================="
echo " RaftPay PHP Demo - Bootstrap"
echo "=============================="
echo

# ─── 1. 检查 PHP ───
if ! command -v php &>/dev/null; then
    fail "未检测到 PHP，请先安装：brew install php"
fi
PHP_VER=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
ok "PHP $PHP_VER"

# ─── 2. 检查必需扩展 ───
REQUIRED_EXTS=(openssl curl json)
for ext in "${REQUIRED_EXTS[@]}"; do
    if php -m 2>/dev/null | grep -qi "^${ext}$"; then
        ok "扩展 $ext 已启用"
    else
        fail "缺少 PHP 扩展: $ext"
    fi
done

# ─── 3. 生成 config.php ───
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
CONFIG_FILE="$SCRIPT_DIR/config.php"
EXAMPLE_FILE="$SCRIPT_DIR/config.example.php"

if [ -f "$CONFIG_FILE" ]; then
    warn "config.php 已存在，跳过生成"
else
    if [ ! -f "$EXAMPLE_FILE" ]; then
        fail "找不到 config.example.php"
    fi
    cp "$EXAMPLE_FILE" "$CONFIG_FILE"
    ok "已从模板生成 config.php，请编辑填入真实凭证"
fi

echo
echo -e "${GREEN}环境就绪！${NC}"
echo "  1. 编辑 config.php 填入商户凭证"
echo "  2. 运行示例：php $SCRIPT_DIR/example.php"
