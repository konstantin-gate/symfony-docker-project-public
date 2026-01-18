# Symfony Modular Suite

**Symfony Modular Suite** je moderní webová aplikace založená na Symfony 8.0, postavená na modulární architektuře. Projekt demonstruje integraci klasického server-side renderingu (Twig) s moderními SPA technologiemi (React) v rámci jednoho monolitu.

Projekt je plně dockerizován a připraven k nasazení.

---

## 📦 Moduly

Aplikace se skládá ze tří nezávislých funkčních modulů:

### 1. Greeting Module (Rozesílání)
Klasický Symfony modul (MVC) pro správu kontaktů a hromadné rozesílání pozdravů.

*   **Funkcionalita:**
    *   Import kontaktů (XML, Text).
    *   Asynchronní odesílání e-mailů přes frontu (Symfony Messenger) s nastavitelnou prodlevou.
    *   Vícejazyčný dashboard a e-mailové šablony.
    *   Validace e-mailových adres.

### 2. Multi-Currency Wallet (Multiměnová peněženka)
Modul pro správu financí implementovaný jako **React SPA** (Single Page Application) vložený do Symfony.

*   **Funkcionalita:**
    *   Evidence zůstatků v různých měnách (CZK, USD, EUR, JPY, BTC a další).
    *   **Přesnost výpočtů:** Použití knihovny `brick/money` pro eliminaci chyb s plovoucí desetinnou čárkou.
    *   **Historie kurzů:** Dynamická tabulka křížových kurzů závislá na zvolené hlavní měně.
    *   **Převodník měn:** Okamžitý přepočet podle aktuálních kurzů.
    *   **Automatická aktualizace:** Integrace s externími API (Exchangerate.host, CurrencyFreaks) s logikou Failover (přepnutí na záložního poskytovatele při výpadku).

### 3. Polygraphy Digest (Inteligentní vyhledávání)
Agregátor novinek a produktů polygrafického průmyslu s výkonným vyhledávacím nástrojem.

*   **Funkcionalita:**
    *   **Agregace:** Automatický sběr dat z RSS a externích webových stránek.
    *   **Chytré vyhledávání:** Fulltextové vyhledávání v Elasticsearch s našeptávačem a zvýrazněním výsledků.
    *   **Analytika:** Výpočet trendů aktivity publikací v reálném čase.
    *   **Rozhraní:** Moderní React rozhraní s fasetovou filtrací.

---

## 🛠 Technologický stack

### Backend
*   **Framework:** Symfony 8.0 (PHP 8.4)
*   **Databáze:** PostgreSQL 16
*   **Vyhledávač:** Elasticsearch 8.x
*   **Cache/Fronta:** KeyDB (kompatibilní s Redis)
*   **ORM:** Doctrine ORM
*   **Fronta:** Symfony Messenger
*   **Matematika:** `brick/money`, `brick/math` (pro finanční operace)

### Frontend
*   **Build Tool:** Webpack Encore
*   **Jádro:**
    *   *Greeting:* Bootstrap 5, Twig, Native JS.
    *   *Wallet:* **React 18**, TypeScript, Tailwind CSS, Shadcn UI.
    *   *Polygraphy:* **React 18**, TypeScript, Tailwind CSS.

### Infrastruktura
*   **Docker:** Nginx, PHP-FPM, Postgres, Elasticsearch, Kibana, KeyDB, Node.js.

---

## 🚀 Instalace a spuštění

### Požadavky
*   Docker a Docker Compose

### Krok 1: Spuštění kontejnerů
Sestavte a spusťte prostředí:
```bash
docker compose up --build -d
```

### Krok 2: Instalace záвисиlostí
Nainstalujte PHP a Node.js závislosti:

```bash
# PHP balíčky
docker compose exec php composer install

# Frontend balíčky
docker compose run --rm node npm install
```

### Krok 3: Nastavení prostředí (.env.local)
Vytvořte soubor `app/.env.local` pro konfiguraci API klíčů a pošty. Toto je kritické pro modul Wallet a odesílání e-mailů.

```dotenv
# --- Nastavení pošty (Greeting Module) ---
MAILER_SENDER_EMAIL=hello@example.com
MAILER_SENDER_NAME="Moje Firma"
# Režim doručení: 'file' (do složky var/mails) nebo 'smtp'
EMAIL_DELIVERY_MODE=file
# Prodleva mezi e-maily (sekundy)
EMAIL_SEQUENCE_DELAY=2

# --- API klíče pro směnné kurzy (Wallet Module) ---
# Získejte bezplatné klíče u příslušných služeb
EXCHANGERATE_HOST_KEY=vas_klic_zde
CURRENCYFREAKS_KEY=vas_klic_zde
```

### Krok 4: Inicializace DB a Sestavení
Spusťte skript pro kompletní inicializaci. Vytvoří databázi, provede migrace a **nahraje fixtures** (testovací data pro peněženku a kurzy).

```bash
# Inicializace DB (Migrace + Fixtures)
docker compose exec php composer db-init

# Inicializace vyhledávacích indexů (Elasticsearch)
docker compose exec php bin/console polygraphy:search:init

# Sestavení frontendu (Dev režim s watch)
docker compose run --rm node npm run dev
```

---

## 🖥 Použití

Po spuštění je aplikace dostupná na adrese: **[http://localhost](http://localhost)**

### Hlavní sekce
*   **Greeting Dashboard:** `/greeting/dashboard`
*   **Multi-Currency Wallet:** `/multi-currency-wallet`
*   **Polygraphy Digest:** `/polygraphy`

### Konzolové příkazy
*   **Worker fronty (Greeting Module):**
    ```bash
    docker compose exec php bin/console messenger:consume async -v
    ```
*   **Worker fronty a plánovač (Polygraphy Module):**
    ```bash
    docker compose exec php bin/console messenger:consume polygraphy scheduler_polygraphy -vv
    ```
*   **Kontrola stavu služeb:**
    ```bash
    docker compose exec php bin/console app:status:list
    ```

---

## 🧪 Vývoj a QA

### Testování
Pro spuštění Unit a Integračních testů (používá samostatnou testovací databázi):

```bash
# Příprava testovací DB (jednou)
./bin/setup-test-db

# Spuštění testů
docker compose exec php bin/phpunit
```

### Kvalita kódu
Projekt je nastaven na přísné standardy kvality:

```bash
# Spustit kompletní QA cyklus (CS Fixer + PHPStan)
docker compose exec php composer qa
```