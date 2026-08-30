#!/usr/bin/env bash
# 多角色对话 · 本地前后端启停脚本
# 用法: ./dev.sh start | stop | restart | status | frontend | backend

set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
RUNTIME="$ROOT/.runtime"
LOG_DIR="$RUNTIME/logs"
PID_DIR="$RUNTIME/pids"

FRONTEND_DIR="$ROOT/frontend"
FRONTEND_PORT="${FRONTEND_PORT:-5173}"
FRONTEND_PID="$PID_DIR/frontend.pid"
FRONTEND_LOG="$LOG_DIR/frontend.log"

# 后端优先用 backend/；尚未迁移时回退到 yingxue MVP
if [[ -d "$ROOT/backend" ]]; then
  BACKEND_DIR="$ROOT/backend"
  BACKEND_NAME="backend"
  # 标准分离版：document root 在 backend 根或 public
  if [[ -f "$BACKEND_DIR/public/index.php" ]]; then
    BACKEND_DOCROOT="$BACKEND_DIR/public"
    BACKEND_ROUTER="$BACKEND_DIR/public/index.php"
  else
    BACKEND_DOCROOT="$BACKEND_DIR"
    BACKEND_ROUTER=""
  fi
else
  BACKEND_DIR="$ROOT/yingxue"
  BACKEND_NAME="yingxue"
  BACKEND_DOCROOT="$BACKEND_DIR/public"
  BACKEND_ROUTER="$BACKEND_DIR/public/index.php"
fi

BACKEND_HOST="${BACKEND_HOST:-127.0.0.1}"
BACKEND_PORT="${BACKEND_PORT:-8080}"
BACKEND_PID="$PID_DIR/backend.pid"
BACKEND_LOG="$LOG_DIR/backend.log"

mkdir -p "$LOG_DIR" "$PID_DIR"

color() { printf '\033[%sm%s\033[0m' "$1" "$2"; }
info()  { echo "$(color 36 '[info]') $*"; }
ok()    { echo "$(color 32 '[ ok ]') $*"; }
warn()  { echo "$(color 33 '[warn]') $*"; }
err()   { echo "$(color 31 '[err ]') $*" >&2; }

is_running() {
  local pid_file="$1"
  [[ -f "$pid_file" ]] || return 1
  local pid
  pid="$(cat "$pid_file" 2>/dev/null || true)"
  [[ -n "${pid:-}" ]] && kill -0 "$pid" 2>/dev/null
}

port_in_use() {
  local port="$1"
  if command -v lsof >/dev/null 2>&1; then
    lsof -nP -iTCP:"$port" -sTCP:LISTEN >/dev/null 2>&1
  else
    return 1
  fi
}

pid_on_port() {
  local port="$1"
  lsof -nP -iTCP:"$port" -sTCP:LISTEN -t 2>/dev/null | head -1 || true
}

start_frontend() {
  if is_running "$FRONTEND_PID"; then
    warn "前端已在运行 (pid $(cat "$FRONTEND_PID")) → http://127.0.0.1:$FRONTEND_PORT"
    return 0
  fi

  if [[ ! -d "$FRONTEND_DIR" ]]; then
    err "找不到前端目录: $FRONTEND_DIR"
    return 1
  fi

  if [[ ! -d "$FRONTEND_DIR/node_modules" ]]; then
    info "首次启动，安装前端依赖…"
    (cd "$FRONTEND_DIR" && npm install)
  fi

  if port_in_use "$FRONTEND_PORT"; then
    local existing
    existing="$(pid_on_port "$FRONTEND_PORT")"
    warn "端口 $FRONTEND_PORT 已被占用 (pid ${existing:-?})，跳过前端启动"
    echo "${existing:-}" > "$FRONTEND_PID"
    return 0
  fi

  info "启动前端 Vite → http://127.0.0.1:$FRONTEND_PORT"
  (
    cd "$FRONTEND_DIR"
    # 固定 host/port，方便脚本启停与代理
    nohup npm run dev -- --host 127.0.0.1 --port "$FRONTEND_PORT" \
      >"$FRONTEND_LOG" 2>&1 &
    echo $! > "$FRONTEND_PID"
  )
  sleep 1
  if is_running "$FRONTEND_PID"; then
    ok "前端已启动 (pid $(cat "$FRONTEND_PID"))，日志: $FRONTEND_LOG"
  else
    err "前端启动失败，查看日志: $FRONTEND_LOG"
    return 1
  fi
}

start_backend() {
  if is_running "$BACKEND_PID"; then
    warn "后端已在运行 (pid $(cat "$BACKEND_PID")) → http://$BACKEND_HOST:$BACKEND_PORT"
    return 0
  fi

  if [[ ! -d "$BACKEND_DIR" ]]; then
    err "找不到后端目录: $BACKEND_DIR"
    return 1
  fi

  if ! command -v php >/dev/null 2>&1; then
    err "未找到 php，请先安装 PHP >= 7.4"
    return 1
  fi

  if port_in_use "$BACKEND_PORT"; then
    local existing
    existing="$(pid_on_port "$BACKEND_PORT")"
    warn "端口 $BACKEND_PORT 已被占用 (pid ${existing:-?})，跳过后端启动"
    echo "${existing:-}" > "$BACKEND_PID"
    return 0
  fi

  info "启动后端 PHP ($BACKEND_NAME) → http://$BACKEND_HOST:$BACKEND_PORT"
  (
    cd "$BACKEND_DIR"
    if [[ -n "$BACKEND_ROUTER" && -f "$BACKEND_ROUTER" ]]; then
      nohup php -S "$BACKEND_HOST:$BACKEND_PORT" -t "$BACKEND_DOCROOT" "$BACKEND_ROUTER" \
        >"$BACKEND_LOG" 2>&1 &
    else
      nohup php -S "$BACKEND_HOST:$BACKEND_PORT" -t "$BACKEND_DOCROOT" \
        >"$BACKEND_LOG" 2>&1 &
    fi
    echo $! > "$BACKEND_PID"
  )
  sleep 0.5
  if is_running "$BACKEND_PID"; then
    ok "后端已启动 (pid $(cat "$BACKEND_PID"))，日志: $BACKEND_LOG"
  else
    err "后端启动失败，查看日志: $BACKEND_LOG"
    return 1
  fi
}

stop_by_pidfile() {
  local name="$1"
  local pid_file="$2"
  local port="${3:-}"

  if is_running "$pid_file"; then
    local pid
    pid="$(cat "$pid_file")"
    info "停止 $name (pid $pid)…"
    # 杀掉进程组，避免留下 npm/vite 子进程
    kill "$pid" 2>/dev/null || true
    # Vite 常经 npm 拉起，再按端口兜底
    if [[ -n "$port" ]]; then
      local p
      p="$(pid_on_port "$port")"
      if [[ -n "$p" ]]; then
        kill "$p" 2>/dev/null || true
        sleep 0.3
        kill -9 "$p" 2>/dev/null || true
      fi
    fi
    # 若主进程还在，强制结束
    if kill -0 "$pid" 2>/dev/null; then
      sleep 0.3
      kill -9 "$pid" 2>/dev/null || true
    fi
    rm -f "$pid_file"
    ok "$name 已停止"
    return 0
  fi

  # pid 文件失效时，按端口清理
  if [[ -n "$port" ]] && port_in_use "$port"; then
    local p
    p="$(pid_on_port "$port")"
    if [[ -n "$p" ]]; then
      info "按端口 $port 停止 $name (pid $p)…"
      kill "$p" 2>/dev/null || true
      sleep 0.3
      kill -9 "$p" 2>/dev/null || true
      ok "$name 已停止"
    fi
  else
    warn "$name 未在运行"
  fi
  rm -f "$pid_file"
}

stop_frontend() { stop_by_pidfile "前端" "$FRONTEND_PID" "$FRONTEND_PORT"; }
stop_backend()  { stop_by_pidfile "后端" "$BACKEND_PID" "$BACKEND_PORT"; }

status_one() {
  local name="$1"
  local pid_file="$2"
  local url="$3"
  local port="$4"

  if is_running "$pid_file"; then
    ok "$name 运行中  pid=$(cat "$pid_file")  $url"
  elif port_in_use "$port"; then
    warn "$name 端口占用中  pid=$(pid_on_port "$port")  $url （非本脚本启动）"
  else
    echo "$(color 90 '[ -- ]') $name 已停止"
  fi
}

cmd_status() {
  echo "── 服务状态 ──"
  status_one "前端" "$FRONTEND_PID" "http://127.0.0.1:$FRONTEND_PORT" "$FRONTEND_PORT"
  status_one "后端($BACKEND_NAME)" "$BACKEND_PID" "http://$BACKEND_HOST:$BACKEND_PORT" "$BACKEND_PORT"
  echo "日志目录: $LOG_DIR"
}

cmd_start() {
  start_backend
  start_frontend
  echo
  cmd_status
}

cmd_stop() {
  stop_frontend
  stop_backend
}

cmd_restart() {
  cmd_stop
  sleep 0.5
  cmd_start
}

usage() {
  cat <<EOF
用法: $(basename "$0") <命令>

命令:
  start       启动后端 + 前端
  stop        停止后端 + 前端
  restart     重启全部
  status      查看状态
  frontend    仅启动前端
  backend     仅启动后端
  stop-fe     仅停止前端
  stop-be     仅停止后端

环境变量:
  FRONTEND_PORT   前端端口，默认 5173
  BACKEND_HOST    后端主机，默认 127.0.0.1
  BACKEND_PORT    后端端口，默认 8080

当前后端目录: $BACKEND_DIR ($BACKEND_NAME)
EOF
}

main() {
  local cmd="${1:-}"
  case "$cmd" in
    start)    cmd_start ;;
    stop)     cmd_stop ;;
    restart)  cmd_restart ;;
    status)   cmd_status ;;
    frontend) start_frontend ;;
    backend)  start_backend ;;
    stop-fe)  stop_frontend ;;
    stop-be)  stop_backend ;;
    -h|--help|help|"") usage ;;
    *)
      err "未知命令: $cmd"
      usage
      exit 1
      ;;
  esac
}

main "$@"
