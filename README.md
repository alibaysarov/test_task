# Тестовое задание: реферальная программа

Ориентир — **15 минут**. Нейросети использовать можно и нужно: Claude, ChatGPT,
Cursor, Copilot — что угодно, мы сами так работаем.

---

## 1. Как проходит задание

**Созвона не будет — не ждите от нас приглашения.** Всё делаете сами, в удобное
вам время:

прочитали ТЗ → поставили инструменты → включили запись экрана → сделали задание →
прислали нам ссылки (как — в конце блока 3).

Записывайте весь процесс целиком: как запускаете проект, что читаете в коде, что
спрашиваете у нейросети, как проверяете результат. Можно с голосом — так ещё лучше.
Нам важен ход работы, а не только итоговый код.

Перед тем как включать запись, поставьте:

| Что | Зачем |
|---|---|
| Программа записи экрана — OBS, Loom или встроенная в систему | записать видео выполнения |
| **Docker Desktop** ([скачать](https://www.docker.com/products/docker-desktop/)) и **запустить его** | в нём поднимается проект, локальный PHP не нужен |
| **Postman** ([скачать](https://www.postman.com/downloads/)) или `curl` | дёргать роуты и смотреть ответы |
| Любой редактор с PHP — VS Code, PhpStorm, Cursor | смотреть и править код |
| Доступ к вашей нейросети | пригодится |

Вместо Docker подойдёт локальный PHP 8.2+ с Composer, если он у вас уже стоит.

Проверьте заранее, что Docker живой:

```bash
docker run --rm hello-world
```

---

## 2. Что в проекте

Мини-сервис реферальной программы MastApp. Мастер приводит другого мастера
по своему коду и получает за это вознаграждение.

В базе три таблицы: `masters` (мастера), `payments` (их платежи за подписку)
и `referrals` (кто кого привёл). Данные уже засеяны: Маша с кодом `MASHA10`
и четверо приведённых ею мастеров с разной историей платежей.

Есть модели, сервис `ReferralService` и обработчик платежей `PaymentObserver`.

---

## 3. Что нужно сделать

Три роута в `routes/api.php`:

| Метод | Путь | Что делает |
|---|---|---|
| `POST` | `/api/referrals/attach` | принимает `{ "code": "MASHA10" }` и закрепляет текущего мастера за владельцем кода. Повторный вызов не создаёт вторую привязку, за себя закрепиться нельзя |
| `GET` | `/api/referrals/my` | список приведённых мной мастеров: имя, дата привязки, засчитан или нет, сколько по нему начислено |
| `GET` | `/api/referrals/earnings` | сводка по деньгам: всего начислено, в ожидании, выплачено, сколько рефералов засчитано |

Текущий мастер приходит в заголовке `X-Master-Id`, авторизация заглушена.
`X-Master-Id: 1` — это Маша.

Формат JSON выбираете сами. Красивая архитектура не нужна — нужно рабочее и честное.

**Два правила мы намеренно не описываем словами:** в какой момент реферал считается
засчитанным и как считается сумма вознаграждения. Оба уже зафиксированы в коде проекта.
Ваши роуты должны вести себя так, как ведёт себя система, а не так, как «обычно бывает
в реферальных программах».

### Как сдать

1. Если ещё не сделали — создайте свой репозиторий из этого шаблона: кнопка
   **Use this template** → **Create a new repository** на странице задания на GitHub.
2. Закоммитьте свою работу и запушьте её в свой репозиторий.
3. Сделайте репозиторий **публичным**, чтобы мы могли открыть его без запроса доступа.
4. Пришлите нам **две ссылки**:
   - на ваш репозиторий;
   - на видео — YouTube (доступ по ссылке), Google Drive, Яндекс Диск, Loom — куда
     удобно, главное чтобы открывалось без запроса доступа.

---

## 4. Запуск

Команды выполняются из папки проекта.

### Шаг 1. Поднять окружение

Docker Compose запускает PHP 8.3-FPM и Nginx. Исходники монтируются в контейнер,
поэтому изменения в PHP-коде применяются без пересборки. Зависимости Composer
собираются на отдельной стадии образа и хранятся в Docker volume.

```bash
cp -n .env.example .env
docker compose up --build -d
```

Приложение будет доступно на `http://localhost:4400`. Порт можно изменить в `.env`,
например `NGINX_PORT=4401`; в таком случае также измените `APP_URL` на тот же порт.
Для локального запуска без Compose используйте порт `8000`. Посмотреть логи можно
командой `docker compose logs -f`, остановить окружение — `docker compose down`.

### Шаг 2. Конфиг и база

Скопируйте конфигурацию и выполните миграции внутри PHP-контейнера:

```bash
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
curl http://localhost:4400/api/ping
```

Контейнер создаёт пустой файл `database/database.sqlite` при первом запуске.
В `.env` уже выбран драйвер SQLite, путь к этому файлу и включены внешние ключи.
`migrate --seed` выполняется один раз при первоначальной настройке. При обычном
перезапуске `APP_KEY` и SQLite-файл сохраняются. Для одноразового сброса демо-данных:

```bash
docker compose exec app php artisan migrate:fresh --seed
```

Проверка и обслуживание внутри контейнера:

```bash
docker compose exec app composer check-platform-reqs
docker compose exec app vendor/bin/phpunit
docker compose exec app composer docs
docker compose logs -f
docker compose down
```

В PowerShell используйте `Copy-Item .env.example .env` вместо первой команды.

### Выполнение команд Artisan

```bash
docker compose exec app php artisan route:list
docker compose exec app composer docs
```

### Альтернатива без Compose

Для локального запуска нужны PHP 8.2+, Composer и расширение `pdo_sqlite`.
Проверьте последнее командой `php -m | findstr /I pdo_sqlite` в PowerShell или
`php -m | grep -i pdo_sqlite` в macOS/Linux.

```bash
composer install
cp -n .env.example .env
touch database/database.sqlite
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

API будет доступно на `http://localhost:8000`.

### Установка зависимостей без локального PHP

```bash
docker run --rm -v "$PWD:/app" -w /app composer:2 composer install
```

В PowerShell вместо `$PWD` подставьте полный путь: `-v "C:\путь\к\проекту:/app"`.
Займёт минуту-две, качается Laravel.

Проверка локального сервера:

```bash
curl http://localhost:8000/api/ping
# {"ok":true}
```

### Шаг 4. Свои роуты

Пишете их, дёргаете Postman'ом с заголовком `X-Master-Id: 1` и смотрите,
сходятся ли числа.

Полезное:

```bash
php artisan migrate:fresh --seed   # пересобрать базу с нуля
php artisan route:list             # посмотреть свои роуты
php artisan tinker                 # покопаться в данных руками
```

Через Docker Compose используйте `docker compose exec app php artisan ...`.

### Документация и Postman

После изменений API выполните из корня проекта (контейнер `app` должен работать):

```bash
make docs
```

В корне появятся `collections.json` (Postman Collection v2.1) и `openapi.yaml`
(OpenAPI). В Postman выберите **Import → Link** и укажите
[`http://localhost:4400/docs.postman`](http://localhost:4400/docs.postman).
Также можно выбрать **Import → Files** и открыть `collections.json`.
OpenAPI также можно импортировать отдельным файлом. Повторный `make docs`
обновляет оба файла; затем импортируйте коллекцию в Postman повторно.
Генерируемые файлы не включаются в Git.

Для локального PHP без Docker: `make docs DOCS_RUNNER=`. Требуется GNU Make.
`composer docs` продолжает генерировать исходные экспорты в
`storage/app/private/scribe/collection.json` и `storage/app/private/scribe/openapi.yaml`.

Проект использует Scribe для генерации документации непосредственно из Laravel-маршрутов.
После изменения роутов, validation rules или ответов выполните:

```bash
composer docs
```

При запуске через Docker Compose:

```bash
docker compose exec app composer docs
```

Команда создаёт HTML-документацию, OpenAPI-схему и Postman Collection. При запущенном
сервере они доступны по адресам:

```text
http://localhost:4400/docs
http://localhost:4400/docs.postman
http://localhost:4400/docs.openapi
```

Postman Collection можно импортировать по URL `http://localhost:4400/docs.postman`.
В коллекции настройте переменную `baseUrl` на адрес запущенного сервера:
`http://localhost:4400` для Compose или `http://localhost:8000` для локального
запуска. Если файл коллекции сгенерирован заново, уже открытая в Postman коллекция
сама не синхронизируется — импортируйте её повторно.

Примеры запросов (при изменённом `NGINX_PORT` замените `4400` на свой порт):

```bash
curl http://localhost:4400/api/ping
# {"ok":true}

curl -H "X-Master-Id: 1" http://localhost:4400/api/referrals/earnings
# {"total_accrued":30000,"pending":30000,"paid":0,"counted_referrals":1}

curl -H "X-Master-Id: 1" http://localhost:4400/api/referrals/my
# data содержит 4 рефералов Маши: Ира засчитана, Оля/Катя/Даша пока нет

curl -X POST http://localhost:4400/api/referrals/attach \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "X-Master-Id: 2" \
  -d '{"code":"MASHA10"}'
# первый успешный вызов: 201 и "created": true; повторный: 200 и "created": false
```

Ожидаемая сводка для демо-данных Маши: всего начислено `30000`, в ожидании
`30000`, выплачено `0`, засчитан `1` реферал. Вознаграждение сохраняется как
`rewardAmount`: сумма платежа умножается на значение `REFERRAL_PERCENT` из конфига,
поэтому при платеже `3000` и проценте `10` начисляется `30000`. Нулевой
`card`-платёж сам по себе не засчитывает реферала; это сохранённое поведение
проекта.
