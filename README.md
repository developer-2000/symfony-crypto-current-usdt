# Symfony 7.4 + Docker

Проект на Symfony 7.4 и PHP 8.3. Запуск через Docker (nginx + PHP-FPM в одном контейнере, MySQL, Redis, phpMyAdmin).

---

## Как запустить проект

Все команды — с файлом **`dev-compose.yml`**:

```bash
# Запуск всех контейнеров (первый раз может занять 1–2 мин — composer install в entrypoint)
docker compose -f dev-compose.yml up -d

# Проверить статус
docker compose -f dev-compose.yml ps

# Остановка
docker compose -f dev-compose.yml down

# останавливает контейнеры и удаляет тома (БД и vendor, полный сброс)
docker compose -f dev-compose.yml down -v

# пересобрать образ app без кэша
docker compose -f dev-compose.yml build --no-cache app    
```

## Снапшот портфеля (только через cron)

Расчёт и запись стоимости портфеля выполняет **крон внутри контейнера** по расписанию. Запускать команду вручную не нужно.

**Проверка работы cron:**
```bash
docker compose -f dev-compose.yml exec app cat /etc/cron.d/portfolio-snapshot
```
Должна вывести строку расписания вида `*/60 * * * * root ...`. Интервал задаётся в `.env` переменной `CRON_SNAPSHOT_MINUTES` (по умолчанию 60).

### Быстрая проверка работы

Чтобы быстро увидеть появление точек на графике и в API, в **`.env`** выставьте:

- **`CRON_SNAPSHOT_MINUTES=1`** — снапшот раз в минуту (вместо раз в час).
- **`PORTFOLIO_HISTORY_DEFAULT_HOURS=2`** — по умолчанию на главной показывать последние 2 часа.

После смены переменных перезапустите контейнеры, чтобы cron подхватил новый интервал:

```bash
docker compose -f dev-compose.yml down
docker compose -f dev-compose.yml up -d
```

Через 1–2 минуты на главной появятся первые точки; можно также вызвать вручную:  
`docker compose -f dev-compose.yml exec app php bin/console app:portfolio:snapshot`

Для продакшена верните в `.env`: `CRON_SNAPSHOT_MINUTES=60`, `PORTFOLIO_HISTORY_DEFAULT_HOURS=24`.

## Миграции БД:

```bash
docker compose -f dev-compose.yml exec app php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
```

## Запуск тестов

Если в контейнере нет `vendor/bin/simple-phpunit`, один раз установите зависимости (в т.ч. dev) в контейнере:

```bash
docker compose -f dev-compose.yml exec app composer install --no-interaction
```

По умолчанию выполняются только юнит-тесты (без БД):

```bash
docker compose -f dev-compose.yml exec app php vendor/bin/simple-phpunit
```

## После запуска откройте **стартовую страницу**:
**http://localhost:8080**

---

## Страницы и сервисы Docker

| Сервис      | URL / подключение        | Описание |
|-------------|---------------------------|----------|
| **Приложение** | http://localhost:8080   | Главная и все маршруты Symfony |
| **phpMyAdmin** | http://localhost:8081   | Веб-интерфейс MySQL (логин: `root`, пароль пустой) |
| **MySQL**   | из контейнера `app`: `db:3306`; с хоста порт не проброшен (при необходимости добавьте в compose `ports: "3306:3306"` у сервиса `db`) | БД |
| **Redis**   | с хоста: `127.0.0.1:6379`; из контейнера: `redis:6379` | Кэш/очереди |

## Полезные команды:

```bash
# PHP/Composer/консоль в контейнере app
docker compose -f dev-compose.yml exec app php -v
docker compose -f dev-compose.yml exec app composer install
docker compose -f dev-compose.yml exec app php bin/console list

# MySQL
docker compose -f dev-compose.yml exec db mysql -uroot -e "SHOW DATABASES;"

# Redis CLI
docker compose -f dev-compose.yml exec redis redis-cli PING
```

---

## Настройки базы данных

- **Хост (из контейнера app):** `db`
- **Порт:** `3306`
- **БД:** `lytvynov_crypto`
- **Пользователь:** `root`
- **Пароль:** пустой

В `dev-compose.yml` для приложения заданы переменные:

- `DATABASE_URL=mysql://root:@db:3306/lytvynov_crypto`
- `DB_DATABASE=lytvynov_crypto`, `DB_USERNAME=root`, `DB_PASSWORD=""`

---

## Redis

- **Из контейнера app:** `redis:6379`
- **С хоста:** `127.0.0.1:6379` (порт 6379 проброшен)

В `.env` можно задать `REDIS_URL=redis://redis:6379` для кэша/очередей (в Docker эти переменные можно переопределить через `environment` в compose).

---

## Переменные окружения (.env)

В корне лежит файл **`.env`**. В нём заданы значения по умолчанию. При запуске в Docker переменные из **`dev-compose.yml`** (секция `environment` у сервиса `app`) **имеют приоритет** над `.env`.

Порядок (кратко): сначала загружаются `.env`, `.env.local`, `.env.$APP_ENV`, `.env.$APP_ENV.local`, затем **переменные окружения контейнера** их переопределяют.

В `.env` имеют смысл:

- **APP_ENV**, **APP_SECRET**, **APP_SHARE_DIR** — Symfony (секреты не хранить в репозитории).
- **DEFAULT_URI** — базовый URL для генерации ссылок в CLI (например, `http://localhost:8080`).
- **DATABASE_URL** — строка подключения к MySQL. В Docker по умолчанию: `mysql://root:@db:3306/lytvynov_crypto`.
- **REDIS_URL** — опционально, для Redis: `redis://redis:6379`.

Локальные переопределения (пароли, другая БД и т.п.) — в **`.env.local`** (файл не коммитить).

---

## Стартовая страница

По адресу **http://localhost:8080** открывается главная страница приложения (маршрут `/`, контроллер `HomeController`).

Если видите 404 — убедитесь, что контейнеры запущены (`docker compose -f dev-compose.yml ps`) и заходите по порту **8080**.
