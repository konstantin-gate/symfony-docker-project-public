# Polygraphy Digest Module (Smart Search & Analytics)

**Polygraphy Digest** is a modern subsystem within the Symfony monolith, designed for aggregation, smart search, and data analysis in the printing industry. The module combines classic Symfony server-side rendering with a dynamic React interface and a powerful Elasticsearch search engine.

## 1. Tech Stack

The module uses advanced technologies to ensure high performance and scalability:

- **Backend:** Symfony 8.0, PHP 8.4.
- **Search Engine:** Elasticsearch 8.x (full-text search, facet filters, aggregations for analytics).
- **Frontend:** React 18, TypeScript, Tailwind CSS + Bootstrap 5.
- **Storage:** PostgreSQL 16 (primary DB), Elasticsearch (search index), KeyDB (queues and cache).
- **Async Processing:** Symfony Messenger + KeyDB (Redis protocol).
- **Scheduling:** Symfony Scheduler (regular data collection planning).
- **Localization:** i18next (support for CS, EN, RU).

## 2. Architectural Concept

The module is implemented using the **Client-Side Rendering (CSR)** principle within the monolith:

1. **Server-side:** The `PolygraphyController` (PHP) serves a base Twig template that acts as a container for the React application.
2. **Client-side:** All data interaction occurs through React, which communicates with the server via a REST API (`PolygraphyApiController`).
3. **Search Layer:** API requests are routed to `SearchService`, which encapsulates the logic for interacting with Elasticsearch (highlighting, aggregations).
4. **Asynchrony:** Data collection and indexing are handled by background processes (Workers), ensuring interface responsiveness.

## 3. Getting Started

To successfully launch the module from scratch, follow these steps:

### Step 1: Infrastructure Setup
Ensure all containers are running (Elasticsearch, KeyDB, Kibana):
```bash
docker compose up -d --build
```

### Step 2: Dependencies and Database
```bash
# Install PHP packages
docker compose exec -T php composer install

# Apply migrations
docker compose exec -T php bin/console doctrine:migrations:migrate
```

### Step 3: Elasticsearch Initialization
Create indices (`polygraphy_articles`, `polygraphy_products`) with configured mapping and analyzers:
```bash
docker compose exec -T php bin/console polygraphy:search:init
```

### Step 4: Frontend Build
```bash
docker compose run --rm node npm install
docker compose run --rm node npm run build
```

### Step 5: Test Data and Sources
1. **Test Indexing:** Create a dummy article to verify search functionality:
   ```bash
   docker compose exec -T php bin/console polygraphy:search:test-index
   ```
2. **Load Real Sources:** Add RSS feeds from fixtures:
   ```bash
   docker compose exec -T php bin/console doctrine:fixtures:load --group=polygraphy --append
   ```

### Application Access
- **Main Interface:** [http://localhost/polygraphy](http://localhost/polygraphy)
- **API Endpoints:** `GET /api/polygraphy/articles`, `GET /api/polygraphy/products`
- **Kibana (v8.x):** [http://localhost:5601](http://localhost:5601) (for index debugging)

## 4. Feature Overview

### 4.1. Dashboard
- **Statistics Widgets:** Active sources, weekly trend (publication dynamics), total number of articles.
- **Source Distribution:** Leaderboard of publications by source.

### 4.2. Search
- **Search Bar:** Full-text search with autocomplete.
- **Facet Filters:** Dynamic filters by source (document counts) and period (Today, Week, Month).
- **Results:** Cards with text highlighting and links to originals.

### 4.3. Settings
- Manage display parameters and visibility of hidden materials (`HIDDEN` status).

## 5. Data Lifecycle and Background Tasks

The data processing flow is fully automated:

1. **Scheduling:** Symfony Scheduler checks sources every minute.
2. **Collection (Crawling):** `CrawlerService` downloads content and saves it to PostgreSQL.
3. **Indexing:** Data is automatically synchronized with Elasticsearch.
4. **Background Execution:** To run the parsing, you must start a worker:
   ```bash
   docker compose exec php bin/console messenger:consume polygraphy scheduler_polygraphy -vv
   ```

## 6. Management Commands

- `polygraphy:search:init` — Create index structures.
- `polygraphy:search:reindex` — Sync data from DB to Elasticsearch.
- `polygraphy:search:reset` — Completely clear and recreate the search layer.
- `polygraphy:search:test-index` — Generate verification data.

## 7. Localization

The module supports three languages (i18next): **Russian (RU)**, **English (EN)**, **Czech (CS)**. The language switcher is available in the top navigation bar.

## 8. Deployment (Docker)

All components (PostgreSQL, Elasticsearch, Redis/KeyDB) are configured in `docker-compose.yml`. Index data is persisted in the `elasticsearch-data` volume, preventing data loss when containers are restarted.
