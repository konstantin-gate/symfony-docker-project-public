# Модуль Polygraphy Digest (Интеллектуальный поиск и аналитика)

**Polygraphy Digest** — это современная подсистема в рамках Symfony-монолита, предназначенная для агрегации, интеллектуального поиска и анализа данных в сфере полиграфической промышленности. Модуль объединяет классический серверный рендеринг Symfony с динамическим интерфейсом на React и мощным поисковым движком Elasticsearch.

## 1. Технологический стек

Модуль использует передовые технологии для обеспечения высокой производительности и масштабируемости:

- **Backend:** Symfony 8.0, PHP 8.4.
- **Search Engine:** Elasticsearch 8.x (полнотекстовый поиск, фасетные фильтры, агрегации для аналитики).
- **Frontend:** React 18, TypeScript, Tailwind CSS + Bootstrap 5.
- **Storage:** PostgreSQL 16 (основная БД), Elasticsearch (поисковый индекс), KeyDB (очереди и кеш).
- **Async Processing:** Symfony Messenger + KeyDB (Redis протокол).
- **Scheduling:** Symfony Scheduler (планирование регулярного сбора данных).
- **Localization:** i18next (поддержка CS, EN, RU).

## 2. Архитектурная концепция

Модуль реализован по принципу **Client-Side Rendering (CSR)** внутри монолита:

1. **Серверная часть:** PHP-контроллер `PolygraphyController` отдает базовый Twig-шаблон, который служит контейнером для React-приложения.
2. **Клиентская часть:** Все взаимодействие с данными происходит через React, который общается с сервером через REST API (`PolygraphyApiController`).
3. **Поисковый слой:** Запросы от API направляются в `SearchService`, который инкапсулирует логику взаимодействия с Elasticsearch (подсветка, агрегации).
4. **Асинхронность:** Сбор и индексация данных вынесены в фоновые процессы (Workers), что обеспечивает отзывчивость интерфейса.

## 3. Запуск и Начало работы

Для успешного запуска модуля с чистого листа выполните следующие шаги:

### Шаг 1: Подготовка инфраструктуры
Убедитесь, что запущены все контейнеры (Elasticsearch, KeyDB, Kibana):
```bash
docker compose up -d --build
```

### Шаг 2: Зависимости и база данных
```bash
# Установка PHP пакетов
docker compose exec -T php composer install

# Применение миграций
docker compose exec -T php bin/console doctrine:migrations:migrate
```

### Шаг 3: Инициализация Elasticsearch
Создание индексов (`polygraphy_articles`, `polygraphy_products`) с настроенным маппингом и анализаторами:
```bash
docker compose exec -T php bin/console polygraphy:search:init
```

### Шаг 4: Сборка Frontend
```bash
docker compose run --rm node npm install
docker compose run --rm node npm run build
```

### Шаг 5: Тестовые данные и Источники
1. **Тестовая индексация:** Создать фиктивную статью для проверки поиска:
   ```bash
   docker compose exec -T php bin/console polygraphy:search:test-index
   ```
2. **Загрузка реальных источников:** Добавить RSS-ленты из фикстур:
   ```bash
   docker compose exec -T php bin/console doctrine:fixtures:load --group=polygraphy --append
   ```

### Доступ к приложению
- **Главный интерфейс:** [http://localhost/polygraphy](http://localhost/polygraphy)
- **API эндпоинты:** `GET /api/polygraphy/articles`, `GET /api/polygraphy/products`
- **Kibana (v8.x):** [http://localhost:5601](http://localhost:5601) (для отладки индексов)

## 4. Обзор функций

### 4.1. Дашборд (Dashboard)
- **Виджеты статистики:** Активные источники, тренд недели (динамика публикаций), общее количество статей.
- **Распределение источников:** Таблица лидеров по количеству публикаций.

### 4.2. Поиск (Search)
- **Поисковая строка:** Полнотекстовый поиск с автодополнением.
- **Фасетные фильтры:** Динамические фильтры по источникам (счетчики документов) и периодам (Сегодня, Неделя, Месяц).
- **Результаты:** Карточки с подсветкой текста и ссылками на оригиналы.

### 4.3. Настройки (Settings)
- Управление параметрами отображения и видимостью скрытых материалов (статус `HIDDEN`).

## 5. Жизненный цикл данных и Фоновая работа

Процесс обработки данных полностью автоматизирован:

1. **Планирование:** Symfony Scheduler раз в минуту проверяет источники.
2. **Сбор (Crawling):** `CrawlerService` загружает контент и сохраняет в PostgreSQL.
3. **Индексация (Indexing):** Данные автоматически синхронизируются с Elasticsearch.
4. **Фоновое выполнение:** Для работы парсинга необходимо запустить воркер:
   ```bash
   docker compose exec php bin/console messenger:consume polygraphy scheduler_polygraphy -vv
   ```

## 6. Команды управления

- `polygraphy:search:init` — Создание структуры индексов.
- `polygraphy:search:reindex` — Синхронизация данных из БД в Elasticsearch.
- `polygraphy:search:reset` — Полная очистка и пересоздание поискового слоя.
- `polygraphy:search:test-index` — Генерация проверочных данных.

## 7. Локализация

Модуль поддерживает три языка (i18next): **Русский (RU)**, **English (EN)**, **Čeština (CS)**. Переключатель доступен в верхней панели управления.

## 8. Развертывание (Docker)

Все компоненты (PostgreSQL, Elasticsearch, Redis/KeyDB) настроены в `docker-compose.yml`. Данные индексов сохраняются в томе `elasticsearch-data`, что предотвращает их потерю при перезапуске контейнеров.
