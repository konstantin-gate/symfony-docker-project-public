# Multi-Currency Wallet Module

**Multi-Currency Wallet** is a modern subsystem within the Symfony monolith designed for managing balances in multiple currencies, conversion, and tracking exchange rate dynamics. The module combines Symfony server-side rendering with a dynamic React SPA interface.

## 1. Technology Stack

- **Backend:** Symfony 8.0, PHP 8.4.
- **Microservices:** Python 3.11, FastAPI (exchange rate forecasting).
- **Frontend:** React 18, TypeScript, shadcn/ui, Tailwind CSS, Recharts (charts).
- **Storage:** PostgreSQL 16 (main DB), KeyDB (caching), Redis (microservice cache).
- **Money Handling:** Brick/Money (precise monetary calculations).
- **ML/Analytics:** Prophet (time series forecasting).
- **Localization:** Symfony Translator (CS, EN, RU support).

## 2. Architectural Concept

The module is implemented using **Client-Side Rendering (CSR)** with microservice analytics:

1. **Server-side (Symfony):** The `WalletController` serves a Twig template with initial data, acting as a container for the React application.
2. **Client-side (React):** All data interaction occurs through React (react-router for navigation), which communicates with the server via REST API.
3. **API Layer (Symfony):** A set of controllers in `Controller/Api` for operations with balances, conversion, rates, and history.
4. **Service Layer (Symfony):** Business logic is encapsulated in services (`CurrencyConverter`, `RateHistoryService`, `ReferenceRateService`, etc.).
5. **Forecasting Microservice (Python/FastAPI):** A separate service for time series analysis and exchange rate forecast generation. Symfony acts as a proxy (`GetForecastController`) between React and the Python service.

## 3. Getting Started

### Step 1: Infrastructure Setup
```bash
docker compose up -d --build
```

### Step 2: Dependencies and Database
```bash
docker compose exec -T php composer install
docker compose exec -T php bin/console doctrine:migrations:migrate
```

### Step 3: Frontend Build
```bash
docker compose run --rm node npm install
docker compose run --rm node npm run build
```

### Step 4: Initial Data (optional)
```bash
docker compose exec -T php bin/console doctrine:fixtures:load --group=wallet --append
```

### Application Access
- **Main Interface:** [http://localhost/multi-currency-wallet](http://localhost/multi-currency-wallet)
- **API Endpoints:**
  - `GET /api/multi-currency-wallet/balances` — Get balances.
  - `POST /api/multi-currency-wallet/convert` — Currency conversion.
  - `GET /api/multi-currency-wallet/reference-rates` — Reference rates.
  - `GET /api/multi-currency-wallet/history` — Rate history for charts.

## 4. Feature Overview

### 4.1. Dashboard
- **Balance Cards:** Display balances in each supported currency (CZK, EUR, USD, RUB, JPY, BTC).
- **Total Balance:** Recalculation of all balances into the selected target currency.
- **Editing:** Inline balance editing on cards.

### 4.2. Converter
- **Input Fields:** Amount, source and target currencies.
- **Swap Button (↔):** Quick reversal of conversion direction.
- **Result:** Display of converted amount and current rate.

### 4.3. Rate History (Rates)
- **Rate Table:** Reference rates with date selection capability.
- **Interactive Chart:** Recharts graph with currency and period selection (7/14/30/90 days).
- **Smart Amount:** Chart is built using "smart" amounts (100 CZK, 1000 JPY) for better readability.
- **Forecast (Smart Trend Forecaster):** Integration with Python/FastAPI microservice for ML-based rate forecasting using the Prophet library. Forecast includes confidence intervals.

### 4.4. Settings
- **Main Currency:** Selection of the wallet's main currency.
- **Auto-update:** Enable/disable automatic loading of current rates.

## 5. Rate Updates

Currency rates are loaded from external providers (`exchangerate.host`, `currencyfreaks.com`) with failover logic:

1. **Manual Update:** "Update Rates" button in the application header.
2. **Automatic Update:** On page load, if more than an hour has passed since the last update and it's past 09:00 Prague time.
3. **Throttling:** Maximum one update per hour to prevent abuse.

## 6. Key Services

| Service                   | Purpose                                                         |
|---------------------------|-----------------------------------------------------------------|
| `CurrencyConverter`       | Currency conversion with cross-rate support via USD.            |
| `ReferenceRateService`    | Generation of reference rates with "smart" amounts.             |
| `RateHistoryService`      | Retrieval of rate history for analytics and charts.             |
| `RateUpdateService`       | Loading rates from external APIs (configurable failover).       |
| `BalanceNormalizerService`| Balance normalization between entities and DTOs.                |

### Python Microservice (FastAPI)

| Component                 | Purpose                                                         |
|---------------------------|-----------------------------------------------------------------|
| `CurrencyForecaster`      | ML model based on Prophet for rate forecasting.                 |
| `GET /forecast/{currency}`| API endpoint for forecast retrieval with Redis caching.         |

## 7. Localization

The module supports three languages: **English (EN)**, **Čeština (CS)**, **Русский (RU)**. Language switcher is available in the sidebar. Translation files are located in `app/translations/MultiCurrencyWallet/`.

## 8. Deployment (Docker)

All components are configured in `docker-compose.yml`:
- **PHP (Symfony):** Main backend.
- **PostgreSQL:** Data storage.
- **KeyDB:** Caching on the Symfony side.
- **FastAPI (Python):** Forecasting microservice (`fastapi` container).
- **Redis:** Forecast cache for Python service.

The module can operate in basic mode (without forecasts) without the Python service.
