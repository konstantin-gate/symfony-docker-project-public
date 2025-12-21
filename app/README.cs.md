# Symfony Greeting App

Tato aplikace je založena na Symfony 8.0 a je určena pro správu seznamu kontaktů a rozesílání pozdravných zpráv (například k Vánocům a Novému roku). Projekt je plně dockerizován a obsahuje nástroje pro vývoj, testování a sestavení.

## Funkce

*   **Správa kontaktů:** Import seznamu e-mailových adres prostřednictvím textového pole nebo **nahráním souboru XML**.
*   **Dashboard:** Ovládací panel (`/greeting/dashboard`) pro prohlížení kontaktů, filtrování podle stavu (nové, odeslané) a jazyka.
*   **Vícejazyčnost:** Plná lokalizace rozhraní (čeština, angličtina, ruština).
*   **Rozesílání:** Asynchronní fronta pro odesílání e-mailů s podporou prodlevy (Delay) pro ochranu reputace odesílatele.
*   **Parsování e-mailů:** Vyhrazené služby `GreetingEmailParser` a `GreetingXmlParser` pro spolehlivé zpracování seznamů adres.
*   **Technologický stack:**
    *   **Backend:** Symfony 8.0, PHP 8.4, Doctrine ORM, Symfony Messenger.
    *   **Databáze:** PostgreSQL 16.
    *   **Frontend:** Webpack Encore, Bootstrap 5, Bootstrap Icons, DataTables (+ Select extension).
    *   **Infrastruktura:** Docker (Nginx, PHP-FPM, Postgres, Node.js helper).

## Požadavky

*   Docker
*   Docker Compose

## Instalace a první spuštění

Pro inicializaci projektu od nuly proveďte následující kroky:

### 1. Spuštění kontejnerů
Sestavte a spusťte Docker kontejnery:

```bash
docker compose up --build -d
```

### 2. Instalace závislostí PHP
Nainstalujte potřebné balíčky přes Composer (provádí se uvnitř kontejneru `php`):

```bash
docker compose exec php composer install
```

### 3. Příprava databáze
Aplikujte migrace pro vytvoření potřebných tabulek (`greeting_contact`, `greeting_log`, `messenger_messages`):

```bash
docker compose exec php bin/console doctrine:migrations:migrate
```

### 4. Sestavení frontendu
Projekt využívá Webpack Encore. Pro instalaci závislostí a sestavení assetů se používá samostatný kontejner `node`.

Instalace NPM balíčků:
```bash
docker compose run --rm node npm install
```

Sestavení assetů pro vývoj (včetně source maps):
```bash
docker compose run --rm node npm run dev
```

*(Pro produkční sestavení použijte `npm run build`)*

---

## Použití

Po úspěšné instalaci bude aplikace dostupná na adrese:
**[http://localhost](http://localhost)**

### Hlavní URL
*   **Domovská stránka:** `http://localhost/`
*   **Ovládací panel (Dashboard):** `http://localhost/greeting/dashboard` (s přesměrováním na lokalizaci, např. `/cs/greeting/dashboard`).

### Import kontaktů
Kontakty můžete importovat dvěma způsoby:
1.  **Textové pole:** Zadejte e-mailové adresy oddělené čárkou, mezerou nebo novým řádkem.
2.  **XML soubor:** Nahrajte soubor s příponou `.xml`. Struktura souboru musí být následující:
    ```xml
    <contacts>
        <email>user1@example.com</email>
        <email>user2@example.com</email>
    </contacts>
    ```

## Konfigurace fronty pro odesílání (Messenger)

Odesílání e-mailů probíhá prostřednictvím asynchronní fronty **Symfony Messenger** s podporou prodlevy mezi zprávami. To umožňuje odesílat velké objemy e-mailů bez blokování rozhraní a přetížení SMTP serveru.

### 1. Spuštění workeru
Aby se e-maily přidané do fronty začaly odesílat, je nutné spustit proces na pozadí (worker).

**V konzoli (pro vývoj):**
```bash
docker compose exec php bin/console messenger:consume async -v
```
Přidejte parametr `--limit=10` pro zpracování pouze 10 zpráv nebo `--time-limit=3600` pro běh po dobu jedné hodiny.

**V produkci:**
Doporučuje se použít Supervisor nebo Systemd pro trvalý běh příkazu `messenger:consume async`.

### 2. Nastavení prodlevy (Delay)
Můžete nastavit interval prodlevy mezi odesíláním e-mailů (v sekundách). To je užitečné pro dodržení limitů poskytovatele (Rate Limiting).

Vytvořte nebo upravte soubor `app/.env.local` a přidejte proměnnou:

```dotenv
# Prodleva v sekundách mezi e-maily (výchozí 1 sekunda)
EMAIL_SEQUENCE_DELAY=5
```

### 3. Nastavení odesílatele
Údaje odesílatele se také nastavují pomocí proměnných prostředí v `app/.env.local`:

```dotenv
MAILER_SENDER_EMAIL=hello@mycompany.com
MAILER_SENDER_NAME="My Company Greeting"
```

### Konzolové příkazy
V projektu je vlastní příkaz pro zobrazení seznamu statusů:

```bash
docker compose exec php bin/console app:status:list
```

## Vývoj a testování

### Spuštění testů
Pro spuštění unit a integračních testů (PHPUnit):

```bash
docker compose exec php bin/phpunit
```

### Kontrola kvality kódu
V `composer.json` jsou nastaveny skripty pro kontrolu stylu kódu (PHP CS Fixer) a statickou analýzu (PHPStan):

```bash
# Spustit vše najednou
docker compose exec php composer qa

# Pouze oprava stylu kódu
docker compose exec php composer cs-fix

# Pouze statická analýza
docker compose exec php composer phpstan
```