# Symfony Greeting App

This is a Symfony 8.0 application designed to manage a contact list and send greeting messages (e.g., for Christmas and New Year). The project is fully containerized with Docker and includes tools for development, testing, and building.

## Features

*   **Contact Management:** Import a list of email addresses via a web interface.
*   **Dashboard:** A control panel (`/greeting/dashboard`) for viewing contacts, filtering by registration date and language.
*   **Multilingual Support:** Supports Czech, English, and Russian languages.
*   **Mailing:** Simulation of sending emails to selected contact groups.
*   **Email Parsing:** A dedicated `GreetingEmailParser` service for reliable processing of address lists (with support for various delimiters).
*   **Technology Stack:**
    *   **Backend:** Symfony 8.0, PHP 8.4, Doctrine ORM.
    *   **Database:** PostgreSQL 16.
    *   **Frontend:** Webpack Encore, Bootstrap 5, Bootstrap Icons.
    *   **Infrastructure:** Docker (Nginx, PHP-FPM, Postgres, Node.js helper).

## Requirements

*   Docker
*   Docker Compose

## Installation and First Run

Follow these steps to initialize the project from scratch:

### 1. Start Containers
Build and start the Docker containers:

```bash
docker compose up --build -d
```

### 2. Install PHP Dependencies
Install the necessary packages via Composer (executed inside the `php` container):

```bash
docker compose exec php composer install
```

### 3. Prepare the Database
Apply migrations to create the necessary tables (`greeting_contact`, `greeting_log`, etc.):

```bash
docker compose exec php bin/console doctrine:migrations:migrate
```

### 4. Build Frontend Assets
The project uses Webpack Encore. A separate `node` container is used to install dependencies and build assets.

Install NPM packages:
```bash
docker compose run --rm node npm install
```

Build assets for development (includes source maps):
```bash
docker compose run --rm node npm run dev
```

*(For production build, use `npm run build`)*

---

## Usage

After successful installation, the application will be available at:
**[http://localhost](http://localhost)**

### Main URLs
*   **Homepage:** `http://localhost/`
*   **Dashboard:** `http://localhost/greeting/dashboard` (with redirection to locale, e.g., `/en/greeting/dashboard`).

### Console Commands
The project includes a custom command to view the list of statuses:

```bash
docker compose exec php bin/console app:status:list
```

## Development and Testing

### Run Tests
To run unit and integration tests (PHPUnit):

```bash
docker compose exec php bin/phpunit
```

### Code Quality Check
In `composer.json`, scripts are configured to check code style (PHP CS Fixer) and static analysis (PHPStan):

```bash
# Run all at once
docker compose exec php composer qa

# Fix code style only
docker compose exec php composer cs-fix

# Static analysis only
docker compose exec php composer phpstan
```
