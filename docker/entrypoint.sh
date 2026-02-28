#!/bin/bash
set -e

mkdir -p /var/run/php
chown www-data:www-data /var/run/php

if [ ! -f /var/www/html/vendor/autoload.php ]; then
    echo "Running composer install..."
    composer install --no-interaction
fi

# Снапшот портфеля: либо цикл каждые N секунд (тест), либо cron каждые N минут
APP_ENV_CRON="${APP_ENV:-prod}"
if [ -n "${SNAPSHOT_INTERVAL_SECONDS}" ] && [ "${SNAPSHOT_INTERVAL_SECONDS}" -gt 0 ] 2>/dev/null; then
  echo "Starting portfolio snapshot loop every ${SNAPSHOT_INTERVAL_SECONDS}s (real Binance -> DB -> Mercure)"
  ( while true; do
      (cd /var/www/html && php bin/console app:portfolio:snapshot --env=${APP_ENV_CRON}) || true
      sleep "${SNAPSHOT_INTERVAL_SECONDS}"
    done ) &
else
  echo "SNAPSHOT_INTERVAL_SECONDS not set or invalid, using cron"
  CRON_MIN="${CRON_SNAPSHOT_MINUTES:-60}"
  CRON_LOG="/var/www/html/var/log/cron_snapshot.log"
  CRON_ENV="/etc/portfolio-snapshot.env"
  mkdir -p /var/www/html/var/log
  touch "$CRON_LOG"
  echo "Cron snapshot: every ${CRON_MIN} min, log: $CRON_LOG"
  # Передать в cron те же env, что и контейнеру (MERCURE_*, APP_ENV), иначе PHP не публикует в Mercure
  {
    echo "export PATH=/usr/local/bin:/usr/bin:/bin"
    echo "export APP_ENV=${APP_ENV_CRON}"
    [ -n "${MERCURE_URL}" ] && echo "export MERCURE_URL=${MERCURE_URL}"
    [ -n "${MERCURE_PUBLIC_URL}" ] && echo "export MERCURE_PUBLIC_URL=${MERCURE_PUBLIC_URL}"
    [ -n "${MERCURE_JWT_SECRET}" ] && echo "export MERCURE_JWT_SECRET=${MERCURE_JWT_SECRET}"
  } > "$CRON_ENV"
  chmod 644 "$CRON_ENV"
  cat > /usr/local/bin/portfolio-snapshot-cron.sh << 'CRONSCRIPT'
#!/bin/bash
set -a
[ -f /etc/portfolio-snapshot.env ] && . /etc/portfolio-snapshot.env
set +a
LOG="/var/www/html/var/log/cron_snapshot.log"
echo "[$(date -Iseconds)] cron snapshot start" >> "$LOG"
cd /var/www/html && php bin/console app:portfolio:snapshot --env="${APP_ENV:-dev}" >> "$LOG" 2>&1
echo "[$(date -Iseconds)] exit=$?" >> "$LOG"
CRONSCRIPT
  chmod +x /usr/local/bin/portfolio-snapshot-cron.sh
  cat > /etc/cron.d/portfolio-snapshot << EOF
SHELL=/bin/bash
PATH=/usr/local/bin:/usr/bin:/bin
*/${CRON_MIN} * * * * root /usr/local/bin/portfolio-snapshot-cron.sh
EOF
  chmod 0644 /etc/cron.d/portfolio-snapshot
  cron
fi

php-fpm -D

# Ждём появления сокета PHP-FPM (избегаем connection reset при первом запросе)
sock="/var/run/php/php-fpm.sock"
for i in $(seq 1 30); do
    [ -S "$sock" ] && break
    sleep 0.2
done
[ ! -S "$sock" ] && echo "WARNING: php-fpm socket not found after 6s" >&2

nginx -g "daemon off;"
