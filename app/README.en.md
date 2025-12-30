# Symfony Greeting App

This is a Symfony 8.0 application designed to manage a contact list and send greeting messages (e.g., for Christmas and New Year). The project is fully containerized with Docker and includes tools for development, testing, and building.

## Features

*   **Contact Management:** Import a list of email addresses via a text field or **by uploading an XML file**.
*   **Dashboard:** A control panel (`/greeting/dashboard`) for viewing contacts, filtering by status (new, sent) and language.
*   **Multilingual Support:** Full localization of the interface (Czech, English, and Russian languages).
*   **Mailing:** Asynchronous email sending queue with delay support to protect sender reputation.
*   **Email Parsing:** Dedicated `GreetingEmailParser` and `GreetingXmlParser` services for reliable processing of address lists.
*   **Technology Stack:**
    *   **Backend:** Symfony 8.0, PHP 8.4, Doctrine ORM, Symfony Messenger.
    *   **Database:** PostgreSQL 16.
    *   **Frontend:** Webpack Encore, Bootstrap 5, Bootstrap Icons, DataTables (+ Select extension).
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
Apply migrations to create the necessary tables (`greeting_contact`, `greeting_log`, `messenger_messages`):

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

### Contact Import
You can import contacts in two ways:
1.  **Text field:** Enter email addresses separated by comma, space, or newline.
2.  **XML file:** Upload a file with the `.xml` extension. The file structure must be as follows:
    ```xml
    <contacts>
        <email>user1@example.com</email>
        <email>user2@example.com</email>
    </contacts>
    ```

## Sending Queue Configuration (Messenger)

Email sending is handled via an asynchronous **Symfony Messenger** queue with delay support between messages. This allows sending large volumes of emails without blocking the interface or overloading the SMTP server.

### 1. Start Worker
To start processing emails added to the queue, you need to run a background worker process.

**In Console (for development):**
```bash
docker compose exec php bin/console messenger:consume async -v
```
Add the `--limit=10` flag to process only 10 messages or `--time-limit=3600` to run for one hour.

**In Production:**
It is recommended to use Supervisor or Systemd to keep the `messenger:consume async` command running permanently.

### 2. Delay Configuration
You can configure the delay interval between emails (in seconds). This is useful for adhering to provider limits (Rate Limiting).

Create or edit the `app/.env.local` file and add the variable:

```dotenv
# Delay in seconds between emails (default is 1 second)
EMAIL_SEQUENCE_DELAY=5
```

### 3. Sender Configuration
Sender details are also configured via environment variables in `app/.env.local`:

```dotenv
MAILER_SENDER_EMAIL=hello@mycompany.com
MAILER_SENDER_NAME="My Company Greeting"
```

### 4. Email Delivery Modes
The application supports two modes for delivering messages: via a real SMTP server or by saving to local files (useful for development).

To switch, change the variable in `app/.env.local`:

```dotenv
# Available values: 'smtp' or 'file'
EMAIL_DELIVERY_MODE=file
```

*   **file:** Emails will be saved to the `app/var/mails/` directory in `.eml` format.
*   **smtp:** Emails will be sent via the configured MAILER_DSN.

### Console Commands
The project includes a custom command to view the list of statuses:

```bash
docker compose exec php bin/console app:status:list
```

## Development and Testing

### Test Database Setup
Before running tests for the first time (or if the test database is missing), run the initialization script from the project root:

```bash
./bin/setup-test-db
```

This script will automatically create the `symfony_db_test` database and apply all necessary migrations.

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