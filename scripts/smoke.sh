#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"; cd "$ROOT"
fail(){ echo "✗ $1" >&2; exit 1; }; ok(){ echo "✓ $1"; }
[[ -f package.json && -f dev.sh && -d frontend && -d backend ]] || fail "缺少核心"
[[ -f .env.example ]] || fail "缺少 .env.example"
ok "核心文件齐全"
if grep -n "admin123" backend/config/global.php >/dev/null 2>&1; then fail "仍含 admin123"; fi
ok "无默认 admin123"
php -l backend/config/global.php >/dev/null
ok "PHP lint OK"
if [[ ! -d frontend/node_modules ]]; then npm install --prefix frontend --no-fund --no-audit; fi
npm run build --prefix frontend
ok "frontend build 通过"
echo ""; echo "smoke: ok"
