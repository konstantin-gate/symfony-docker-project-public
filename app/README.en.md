# Symfony Modular Suite

**Symfony Modular Suite** is a modern web application built on Symfony 8.0, featuring a modular architecture. The project demonstrates the integration of classic server-side rendering (Twig) with modern SPA technologies (React) within a single monolith.

The project is fully containerized and ready for deployment.

---

## 📦 Modules

The application consists of two independent functional modules:

### 1. Greeting Module (Mailing)
A classic Symfony module (MVC) for contact management and mass greeting delivery.

*   **Functionality:**
    *   Contact import (XML, Text).
    *   Asynchronous email sending via queue (Symfony Messenger) with configurable delay.
    *   Multilingual dashboard and email templates.
    *   Email address validation.

### 2. Multi-Currency Wallet
A financial management module implemented as a **React SPA** (Single Page Application) embedded within Symfony.

*   **Functionality:**
    *   Balance tracking in various currencies (CZK, USD, EUR, JPY, BTC, etc.).
    *   **Calculation Precision:** Uses the `brick/money` library to eliminate floating-point errors.
    *   **Rates History:** Dynamic cross-rate table that depends on the selected main currency.
    *   **Currency Converter:** Instant conversion using real-time rates.
    *   **Auto-Update:** Integration with external APIs (Exchangerate.host, CurrencyFreaks) with failover logic (switches to a backup provider on failure).

---

## 🛠 Technology Stack

### Backend
*   **Framework:** Symfony 8.0 (PHP 8.4)
*   **Database:** PostgreSQL 16
*   **ORM:** Doctrine ORM
*   **Queue:** Symfony Messenger (Doctrine transport)
*   **Math:** `brick/money`, `brick/math` (for financial operations)

### Frontend
*   **Build Tool:** Webpack Encore
*   **Core:**
    *   *Greeting:* Bootstrap 5, Twig, Native JS.
    *   *Wallet:* **React 18**, TypeScript, Tailwind CSS, Shadcn UI.

### Infrastructure
*   **Docker:** Nginx, PHP-FPM, Postgres, Node.js (for asset building).

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

### Step 3: Environment Setup (.env.local)
Create an `app/.env.local` file to configure API keys and mail settings. This is critical for the Wallet module and email delivery.

```dotenv
# --- Mail Settings (Greeting Module) ---
MAILER_SENDER_EMAIL=hello@example.com
MAILER_SENDER_NAME="My Company"
# Delivery mode: 'file' (to var/mails folder) or 'smtp'
EMAIL_DELIVERY_MODE=file
# Delay between emails (sec)
EMAIL_SEQUENCE_DELAY=2

# --- API Keys for Exchange Rates (Wallet Module) ---
# Get free keys from the respective services
EXCHANGERATE_HOST_KEY=your_key_here
CURRENCYFREAKS_KEY=your_key_here
```

### Step 4: DB Initialization and Build
Run the full initialization script. It will create the database, run migrations, and **load fixtures** (test data for the wallet and exchange rates).

```bash
# DB Initialization (Migrations + Fixtures)
docker compose exec php composer db-init

# Build Frontend (Dev mode with watch)
docker compose run --rm node npm run dev
```

---

## 🖥 Usage

After startup, the application is available at: **[http://localhost](http://localhost)**

### Main Sections
*   **Greeting Dashboard:** `/greeting/dashboard`
*   **Multi-Currency Wallet:** `/multi-currency-wallet`

### Console Commands
*   **Queue Worker (sending emails):**
    ```bash
    docker compose exec php bin/console messenger:consume async -v
    ```
*   **Service Status Check:**
    ```bash
    docker compose exec php bin/console app:status:list
    ```

---

## 🧪 Development and QA

### Testing
To run Unit and Integration tests (uses a separate test database):

```bash
# Prepare test DB (once)
./bin/setup-test-db

# Run tests
docker compose exec php bin/phpunit
```

### Code Quality
The project is configured with strict quality standards:

```bash
# Run full QA cycle (CS Fixer + PHPStan)
docker compose exec php composer qa
```
