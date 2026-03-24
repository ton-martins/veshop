#!/usr/bin/env sh
set -e

is_disabled() {
    case "$(printf '%s' "$1" | tr '[:upper:]' '[:lower:]')" in
        0|false|no|off|disabled) return 0 ;;
        *) return 1 ;;
    esac
}

if is_disabled "${QUEUE_WORKER_ENABLED:-true}"; then
    echo "[queue-worker] Desabilitado por QUEUE_WORKER_ENABLED."
    exec sh -c "while true; do sleep 3600; done"
fi

CONNECTION="${QUEUE_WORKER_CONNECTION:-${QUEUE_CONNECTION:-redis}}"
QUEUES="${QUEUE_WORKER_QUEUES:-default}"
SLEEP="${QUEUE_WORKER_SLEEP:-1}"
TRIES="${QUEUE_WORKER_TRIES:-3}"
TIMEOUT="${QUEUE_WORKER_TIMEOUT:-300}"
BACKOFF="${QUEUE_WORKER_BACKOFF:-3}"
MAX_TIME="${QUEUE_WORKER_MAX_TIME:-3600}"

if [ "${CONNECTION}" = "sync" ]; then
    echo "[queue-worker] Conexao 'sync' nao processa fila em background. Configure QUEUE_CONNECTION/QUEUE_WORKER_CONNECTION para redis ou database."
    exec sh -c "while true; do sleep 3600; done"
fi

echo "[queue-worker] Iniciando worker (connection=${CONNECTION}, queues=${QUEUES})"
exec php artisan queue:work "${CONNECTION}" \
    --queue="${QUEUES}" \
    --sleep="${SLEEP}" \
    --tries="${TRIES}" \
    --timeout="${TIMEOUT}" \
    --backoff="${BACKOFF}" \
    --max-time="${MAX_TIME}" \
    --no-interaction \
    --verbose