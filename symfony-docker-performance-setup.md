# Docker: быстрая работа и план настройки

## Requirements

- PHP 8.3
- Composer 2.x
- PHP 8.3 + Symfony 7.4 LTS — правильный и современный стек

Работаем с php:8.3-fpm, php:8.3-fpm-alpine
Если где-то встретишь команды для Laravel в этом файле - измени их на команды для Symfony

---

## Главная особенность скорости (обязательно на Windows)

**На Windows bind mount `.:/var/www/html` даёт очень медленный доступ к файлам.** Каждый запрос Symfony 7.4 читает сотни файлов (автолоад, конфиг, вьюхи) с диска хоста → **3–4 секунды на запрос**.

**Решение:** вынести **`vendor` в отдельный named volume** (`app_vendor:/var/www/html/vendor`). Код приложения остаётся на bind mount (редактируешь на хосте), а все зависимости читаются из тома внутри контейнера — **переход между страницами ~300–400 мс** вместо 3–4 с.

В compose:
```yaml
volumes:
  - .:/var/www/html
  - app_vendor:/var/www/html/vendor
volumes:
  app_vendor:
```
В entrypoint при первом запуске: если нет `vendor/autoload.php` — выполнить `composer install`. В образе должен быть Composer (COPY --from=composer:2).

Без этого тома для vendor на Windows быстрый Docker не получить.

---

## Как запустить проект

**Файл:** `dev-compose.yml`. Все команды: `docker compose -f dev-compose.yml <cmd>`.

**Запуск / остановка:**
```bash
docker compose -f dev-compose.yml ps    # проверить, запущены ли контейнеры
docker compose -f dev-compose.yml up -d
docker compose -f dev-compose.yml down
```

**URL и порты:**

| Сервис    | URL / подключение | Нюанс |
|-----------|-------------------|--------|
| Главная   | http://localhost:8080 | Порт в compose `ports: "8080:80"`. |
| phpMyAdmin | http://localhost:8081 | Логин: root, пароль пустой (как в `db`). Порт в compose `8081:80`. |
| MySQL     | Из app: `db:3306`. С хоста: порт не проброшен; при необходимости добавить у `db`: `ports: - "3306:3306"`. |
| Redis     | С хоста: `127.0.0.1:6379`. Из app: `redis:6379`. |

**Redis — смотреть, что происходит:**
```bash
docker compose -f dev-compose.yml exec redis redis-cli
```
Внутри redis-cli: `PING`, `INFO`, `MONITOR` (поток команд в реальном времени), `KEYS *`, `GET key`.

**Использование пакетов контейнера (exec):**
```bash
# app (PHP, Composer, Symfony 7.4)
docker compose -f dev-compose.yml exec app php -v
docker compose -f dev-compose.yml exec app composer install
docker compose -f dev-compose.yml exec app php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
docker compose -f dev-compose.yml exec app php bin/console

# db (MySQL)
docker compose -f dev-compose.yml exec db mysql -uroot -e "SHOW DATABASES;"

# redis
docker compose -f dev-compose.yml exec redis redis-cli PING
```

**Пересборка образа app** (после правок Dockerfile или конфигов в `docker/`):
```bash
docker compose -f dev-compose.yml down
docker compose -f dev-compose.yml build --no-cache app
docker compose -f dev-compose.yml up -d
```
После `composer update` на хосте: `docker compose -f dev-compose.yml exec app composer install`.

---

## Образ app: что обязательно должно быть установлено

Чтобы после клона репозитория **ничего не допиливать** (миграции, консоль, БД работают сразу):

**В Dockerfile в образе app обязательно:**
- Базовый образ: `php:8.3-fpm-bookworm`.
- Удаление `zz-docker.conf` (иначе listen=9000 перезапишет unix socket).
- Системные пакеты: `nginx`, `unzip`, `git`, **`cron`** (для снапшота портфеля по расписанию).
- **PHP-расширение для MySQL:** без него `doctrine:migrations:migrate` падает с ошибкой «could not find driver». Обязательно:
  - установить `default-libmysqlclient-dev` (или аналог под свой образ);
  - выполнить `docker-php-ext-install pdo_mysql`.
- Composer: `COPY --from=composer:2 /usr/bin/composer`.
- Конфиги: `docker/php/www.conf`, `docker/php/php.ini`, `docker/nginx/default.conf`.
- Entrypoint: создание `/var/run/php`, при отсутствии `vendor/autoload.php` — `composer install`; формирование `/etc/cron.d/portfolio-snapshot` из env `CRON_SNAPSHOT_MINUTES` (минуты) и `APP_ENV`, запуск cron, затем php-fpm и nginx.

**В проекте по умолчанию (чтобы миграции были «как в Laravel»):**
- Пакеты: `doctrine/doctrine-bundle`, `doctrine/orm`, `doctrine/doctrine-migrations-bundle`, **`symfony/monolog-bundle`** (в composer.json и установлены в образе через `composer install` в entrypoint).
- Конфиг: `config/packages/doctrine.yaml` (url: `%env(resolve:DATABASE_URL)%`), `config/packages/doctrine_migrations.yaml` (migrations_paths: `DoctrineMigrations` → `migrations/`).
- В `.env` (и при необходимости в `dev-compose.yml` environment): `DATABASE_URL` с корректной строкой MySQL (хост `db` для контейнера).
- Папка `migrations/` в репозитории (пустая или с миграциями).

Тогда после `up -d` и одной команды `doctrine:migrations:migrate --no-interaction --allow-no-migration` БД готова, без ручной установки драйверов или пакетов.

---

## Быстрый старт в новом проекте (чеклист)

Скопировать в корень нового Symfony 7.4-проекта:

| Файл/каталог | Назначение |
|--------------|------------|
| `dev-compose.yml` | Compose: app (build .), db, redis, сеть, **обязательно том `app_vendor:/var/www/html/vendor`** |
| `Dockerfile` | PHP-FPM + nginx в одном образе, Composer, **обязательно `pdo_mysql`** (см. раздел «Образ app»), удаление `zz-docker.conf` |
| `docker/entrypoint.sh` | Создание `/var/run/php`, при отсутствии vendor — `composer install`, затем php-fpm и nginx |
| `docker/nginx/default.conf` | root `public`, fastcgi на unix socket, буферы, `access_log off` |
| `docker/php/www.conf` | pool: unix socket `/var/run/php/php-fpm.sock`, pm=static, max_children=100 |
| `docker/php/php.ini` | OPcache, realpath_cache, revalidate_freq=10 (на Windows) |
| `.dockerignore` | vendor, node_modules, .env, .git |

**В проекте должны быть установлены и настроены по умолчанию:** Doctrine (doctrine-bundle, orm, doctrine-migrations-bundle), **Monolog (symfony/monolog-bundle)** — логи по умолчанию в контейнере; конфиги doctrine.yaml и doctrine_migrations.yaml, `config/packages/monolog.yaml`, папка `migrations/`, переменная `DATABASE_URL` в .env/compose.

**Подставить в `dev-compose.yml`:**
- Имя БД, пользователь, пароль (environment у `app` и `db`).
- Порт приложения (например `8080:80`), при необходимости порт для другого проекта.

**Первый запуск:**
1. `docker compose -f dev-compose.yml up -d` — при первом старте entrypoint выполнит `composer install` (1–2 мин).
2. `docker compose -f dev-compose.yml exec app php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration` — применить миграции. Флаг `--allow-no-migration` обязателен при пустой папке `migrations/` (иначе ошибка «no registered migrations»). В образе должен быть `pdo_mysql` (см. раздел «Образ app»).
3. Открыть http://localhost:8080

---

## Главные составляющие быстрой работы

### 1. Nginx

- **Один контейнер с PHP** — nginx и php-fpm в одном образе, нет лишней сети.
- **FastCGI через Unix socket** — `fastcgi_pass unix:/var/run/php/php-fpm.sock` (не TCP).
- **Буферы** — `fastcgi_buffers 16 32k`, `fastcgi_buffer_size 64k`, `fastcgi_busy_buffers_size 64k`, `fastcgi_read_timeout 240s`.
- **Статика** — отдельный `location` с `expires 365d`, `Cache-Control`.
- **try_files** — `try_files $uri /index.php$is_args$args`.

### 2. PHP (PHP-FPM)

- **pm = static**, **pm.max_children = 100** (dev) — воркеры уже запущены.
- **listen** — unix socket `/var/run/php/php-fpm.sock`.
- **OPcache:** enable=1, memory_consumption=128, max_accelerated_files=7963, interned_strings_buffer=8, **revalidate_freq=10** (на Windows реже проверка = меньше обращений к bind mount), enable_cli=1.
- **realpath_cache_size = 4096K**, **realpath_cache_ttl = 600**.
- **pm.max_requests = 500**.

### 3. MySQL

- **Named volume** для данных (`db_data:/var/lib/mysql`), не bind mount.
- **innodb_buffer_pool_size=2G** (dev), **innodb_log_buffer_size=32M**, **max_allowed_packet=64M**.
- Одна сеть с app, доступ по имени сервиса `db`.

### 4. Redis (опционально)

- В том же compose, та же сеть. Подключение по имени `redis:6379`.

### 5. Общее

- Все сервисы в **одной user-defined сети** (`rnp_network`).
- **depends_on: db** (и redis при наличии) у app.
- Код — bind mount для правки; **vendor на Windows — только в named volume**.

---

## План настройки по пунктам

### 1. Nginx

- Один контейнер с PHP или два с общением через **unix socket** (не TCP).
- В конфиге: `fastcgi_pass unix:/var/run/php/php-fpm.sock;`, буферы 16 32k, buffer_size 64k, busy_buffers 64k, read_timeout 240s.
- Статика: `expires` + `Cache-Control`.
- На Windows: `access_log off` — меньше записей на медленный том.

### 2. PHP (PHP-FPM)

- **Сокет:** `listen = /var/run/php/php-fpm.sock` (каталог создаётся в entrypoint, chown www-data).
- В образе **удалить** `zz-docker.conf` (в нём listen=9000, перетирает сокет).
- **В образе обязательно установить расширение pdo_mysql** (иначе Doctrine и миграции дадут «could not find driver»): пакет `default-libmysqlclient-dev`, затем `docker-php-ext-install pdo_mysql`.
- **Режим:** pm=static, pm.max_children=50–100 (dev).
- **OPcache:** enable, memory_consumption 128, max_accelerated_files 7963, interned_strings_buffer 8, **revalidate_freq 10** (dev на Windows) или 60 (prod), enable_cli 1.
- **realpath_cache:** 4096K, ttl 600.
- Лимиты: memory_limit, max_execution_time, upload_max_filesize, post_max_size.

### 3. MySQL

- **command:** `--innodb_buffer_pool_size=2G` (dev), `--innodb_log_buffer_size=32M`, `--max_allowed_packet=64M`, charset utf8mb4.
- Данные в **named volume**, не bind mount.
- Порт наружу не публиковать, доступ из контейнеров по имени.

### 4. Redis / phpMyAdmin

- Redis в той же сети. phpMyAdmin при необходимости: отдельный сервис, depends_on db, та же сеть.

### 5. Volumes и порядок старта

- **Код:** bind mount `.:/var/www/html`.
- **На Windows обязательно:** named volume для `/var/www/html/vendor`, иначе 3–4 с на запрос.
- **БД и кэш:** только named volumes.
- **depends_on:** у app указать db (и redis при наличии).

---

Итог: быстрая работа — **статичные FPM-воркеры**, **OPcache и realpath_cache**, **nginx ↔ PHP через unix socket**, **большой InnoDB buffer pool**, **одна сеть**. **На Windows критично: vendor в named volume** — без этого запросы остаются 3–4 с.

---

## Фиксация изменений (реализация в проекте)

*При любом изменении Docker/конфигов — дописывать сюда.*

- **Проект:** Symfony 7.4, PHP 8.3. Чеклист файлов: `dev-compose.yml`, `Dockerfile`, `docker/entrypoint.sh`, `docker/nginx/default.conf`, `docker/php/www.conf`, `docker/php/php.ini`, `.dockerignore`.
- **Compose:** `dev-compose.yml`; команды: `docker compose -f dev-compose.yml up -d` / `down`. Перед пересборкой — `down`, затем `build --no-cache app`, затем `up -d`.
- **Главная причина скорости на Windows:** том **`app_vendor:/var/www/html/vendor`** — переходы ~300–400 мс вместо 3–4 с.
- **Сокет PHP-FPM:** `unix:/var/run/php/php-fpm.sock`. В entrypoint: `mkdir -p /var/run/php`, `chown www-data:www-data`, затем при отсутствии `vendor/autoload.php` — `composer install`, затем php-fpm и nginx.
- **Dockerfile:** образ на базе `php:8.3-fpm-bookworm`; установка nginx, unzip, git; **обязательно** пакет `default-libmysqlclient-dev` и `docker-php-ext-install pdo_mysql` (без этого миграции падают с «could not find driver»); Composer (`COPY --from=composer:2`); удаление `zz-docker.conf`.
- **Doctrine и миграции по умолчанию:** в проекте установлены `doctrine/doctrine-bundle`, `doctrine/orm`, `doctrine/doctrine-migrations-bundle`; конфиги `config/packages/doctrine.yaml` (url из DATABASE_URL), `config/packages/doctrine_migrations.yaml` (migrations_paths: `migrations/`); папка `migrations/` в репозитории; в .env и/или compose задан `DATABASE_URL`. После клона: `up -d`, затем `doctrine:migrations:migrate --no-interaction --allow-no-migration`.
- **Monolog:** в проекте установлен **`symfony/monolog-bundle`** — он по умолчанию в контейнере (ставится через `composer install` в entrypoint). Конфиг: `config/packages/monolog.yaml`. Класс **`App\Service\LogManager`** даёт динамические логгеры: `$logManager->getLogger('tasks', 'file')` — лог в `var/log/tasks.log`, `$logManager->getLogger('logger_audit', 'db')` — запись в таблицу `app_log`. Для типа `db` применена миграция с таблицей `app_log` (channel, level, message, context, created_at).
- **Nginx:** `access_log off`; fastcgi на unix socket, буферы по документу.
- **OPcache:** `revalidate_freq = 10` (dev).
- **БД (dev):** `DB_DATABASE=lytvynov_crypto`, `DB_USERNAME=root`, пустой пароль; MySQL с `MYSQL_ALLOW_EMPTY_PASSWORD=1`.
