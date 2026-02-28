# Symfony 7.4 + Docker

**Описание функциональности проекта:** [AboutProject.md](AboutProject.md)

Проект на Symfony 7.4 и PHP 8.3.
Запуск через Docker (nginx + PHP-FPM в одном контейнере, MySQL, Mercure, phpMyAdmin).
БД — **Doctrine ORM** и **Doctrine Migrations**.
Логирование — Monolog и **LogManager** (динамические логгеры: файл и запись в БД `app_log`).

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

## Миграции БД:

```bash
docker compose -f dev-compose.yml exec app php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
```

## Снапшот портфеля (только через cron)

Расчёт и запись стоимости портфеля выполняет **крон внутри контейнера** по расписанию. Запускать команду вручную не нужно.

**Проверка работы cron:**
```bash
docker compose -f dev-compose.yml exec app cat /etc/cron.d/portfolio-snapshot
```
Должна вывести строку расписания вида `*/60 * * * * root ...`. Интервал задаётся в `.env` переменной `CRON_SNAPSHOT_MINUTES` (по умолчанию 60).

### Быстрая проверка работы

Чтобы быстро увидеть появление точек на графике и в API вызвать вручную:  
`docker compose -f dev-compose.yml exec app php bin/console app:portfolio:snapshot`

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

## Полезные команды:

```bash
# PHP/Composer/консоль в контейнере app
docker compose -f dev-compose.yml exec app php -v
docker compose -f dev-compose.yml exec app composer install
docker compose -f dev-compose.yml exec app php bin/console list

# MySQL
docker compose -f dev-compose.yml exec db mysql -uroot -e "SHOW DATABASES;"
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

## Файлы конфигов для работы проекта:

- **`.env`** — переменные окружения (портфель, Binance, БД, Mercure, cron и т.д.). В репозитории — пример/значения по умолчанию.
- **`.env.local`** — локальные переопределения (пароли, другая БД и т.п.); не коммитить.
- **`config/services.yaml`** — регистрация сервисов и параметров приложения.
- **`config/routes.yaml`** — подключение веб- и API-маршрутов.
- **`config/packages/`** — пакетные конфиги: `framework.yaml`, `doctrine.yaml`, `doctrine_migrations.yaml`, `monolog.yaml`, `twig.yaml`, `routing.yaml`, `cache.yaml`, `portfolio.yaml`, `portfolio_history_api.yaml`, `mercure.yaml`, `error_handling.yaml`, `cron.yaml`.
- **`config/routes/framework.yaml`** — маршруты фреймворка (при необходимости).

---
