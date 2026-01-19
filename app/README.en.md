# Symfony Modular Suite

**Symfony Modular Suite** is a modern web application based on Symfony 8.0, built with modular architecture. The project demonstrates the integration of classic server-side rendering (Twig) with modern SPA technologies (React) within a single monolith.

The project is fully dockerized and ready for deployment.

---

## 📦 Modules

The application consists of three independent functional modules:

### 1. Greeting Module
Classic Symfony module (MVC) for contact management and mass greeting distribution.

*   **Features:**
    *   Contact import (XML, Text).
    *   Asynchronous email sending via queue (Symfony Messenger) with configurable delay.
    *   Multilingual dashboard and email templates.
    *   Email address validation.

### 2. Multi-Currency Wallet
Financial management module implemented as a **React SPA** (Single Page Application) embedded in Symfony.

*   **Features:**
    *   Balance tracking in multiple currencies (CZK, USD, EUR, JPY, BTC, etc.).
    *   **Calculation Precision:** Using `brick/money` library to eliminate floating-point errors.
    *   **Rate History:** Dynamic cross-rate table dependent on the selected base currency.
    *   **Interactive Charts:** Visualization of rate history using Recharts (7/14/30/90 days).
    *   **Currency Converter:** Instant conversion using current rates.
    *   **Auto-update:** Integration with external APIs (Exchangerate.host, CurrencyFreaks) with Failover logic (switching to backup provider on failure).
    *   **Smart Trend Forecaster:** ML-based rate forecasting using Python/FastAPI microservice with Prophet library. Forecast includes confidence intervals.

### 3. Polygraphy Digest
News and product aggregator for the printing industry with a powerful search engine.

*   **Features:**
    *   **Aggregation:** Automatic data collection from RSS and external sites.
    *   **Smart Search:** Full-text search in Elasticsearch with autocomplete and result highlighting.
    *   **Analytics:** Real-time calculation of publication activity trends.
    *   **Interface:** Modern React interface with faceted filtering.

---

## 🛠 Technology Stack

### Backend
*   **Framework:** Symfony 8.0 (PHP 8.4)
*   **Microservices:** Python 3.11, FastAPI (currency rate forecasting)
*   **Database:** PostgreSQL 16
*   **Search Engine:** Elasticsearch 8.x
*   **Cache/Queue:** KeyDB (Redis-compatible), Redis (forecast cache)
*   **ORM:** Doctrine ORM
*   **Queue:** Symfony Messenger
*   **Math:** `brick/money`, `brick/math` (for financial operations)
*   **ML/Analytics:** Prophet (time series forecasting)

### Frontend
*   **Build Tool:** Webpack Encore
*   **Core:**
    *   *Greeting:* Bootstrap 5, Twig, Native JS.
    *   *Wallet:* **React 18**, TypeScript, Tailwind CSS, Shadcn UI.
    *   *Polygraphy:* **React 18**, TypeScript, Tailwind CSS.

### Infrastructure
*   **Docker:** Nginx, PHP-FPM, Postgres, Elasticsearch, Kibana, KeyDB, Redis, FastAPI (Python), Node.js.

---

## 🚀 Installation and Setup

### Prerequisites
*   Docker and Docker Compose

### Step 1: Start Containers
Build and start the environment:
```bash
docker compose up --build -d
```

### Step 2: Install Dependencies
Install PHP and Node.js dependencies:

```bash
# PHP packages
docker compose exec php composer install

# Frontend packages
docker compose run --rm node npm install
```

### Step 3: Environment Configuration (.env.local)
Create `app/.env.local` file for API keys and mail configuration. This is critical for the Wallet module and email sending.

```dotenv
# --- Mail Settings (Greeting Module) ---
MAILER_SENDER_EMAIL=hello@example.com
MAILER_SENDER_NAME="My Company"
# Delivery mode: 'file' (to var/mails folder) or 'smtp'
EMAIL_DELIVERY_MODE=file
# Delay between emails (seconds)
EMAIL_SEQUENCE_DELAY=2

# --- API Keys for Exchange Rates (Wallet Module) ---
# Get free keys from respective services
EXCHANGERATE_HOST_KEY=your_key_here
CURRENCYFREAKS_KEY=your_key_here
```

### Step 4: Database Initialization and Build
Run the full initialization script. It will create the database, run migrations, and **load fixtures** (test data for wallet and exchange rates).

```bash
# Database initialization (Migrations + Fixtures)
docker compose exec php composer db-init

# Initialize search indexes (Elasticsearch)
docker compose exec php bin/console polygraphy:search:init

# Frontend build (Dev mode with watch)
docker compose run --rm node npm run dev
```

---

## 🖥 Usage

After startup, the application is available at: **[http://localhost](http://localhost)**

### Main Sections
*   **Greeting Dashboard:** `/greeting/dashboard`
*   **Multi-Currency Wallet:** `/multi-currency-wallet`
*   **Polygraphy Digest:** `/polygraphy`

### Console Commands
*   **Queue Worker (Greeting Module):**
    ```bash
    docker compose exec php bin/console messenger:consume async -v
    ```
*   **Queue Worker and Scheduler (Polygraphy Module):**
    ```bash
    docker compose exec php bin/console messenger:consume polygraphy scheduler_polygraphy -vv
    ```
*   **Service Status Check:**
    ```bash
    docker compose exec php bin/console app:status:list
    ```

---

## 🧪 Development and QA

### Testing
To run Unit and Integration tests (uses separate test database):

```bash
# Test DB preparation (once)
./bin/setup-test-db

# Run tests
docker compose exec php bin/phpunit
```

### Code Quality
The project is configured for strict quality standards:

```bash
# Run full QA cycle (CS Fixer + PHPStan)
docker compose exec php composer qa
```
