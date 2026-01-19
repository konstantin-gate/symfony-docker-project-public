# Symfony Modular Suite

**Symfony Modular Suite** je moderní webová aplikace postavená na Symfony 8.0 s modulární architekturou. Projekt demonstruje integraci klasického server-side renderingu (Twig) s moderními SPA technologiemi (React) v rámci jednoho monolitu.

Projekt je plně dockerizovaný a připravený k nasazení.

---

## 📦 Moduly

Aplikace se skládá ze tří nezávislých funkčních modulů:

### 1. Greeting Module (Rozesílky)
Klasický Symfony modul (MVC) pro správu kontaktů a hromadné rozesílání přání.

*   **Funkcionalita:**
    *   Import kontaktů (XML, Text).
    *   Asynchronní odesílání emailů přes frontu (Symfony Messenger) s nastavitelným zpožděním.
    *   Vícejazyčný dashboard a emailové šablony.
    *   Validace emailových adres.

### 2. Multi-Currency Wallet (Víceměnová peněženka)
Modul pro správu financí implementovaný jako **React SPA** (Single Page Application) vestavěný do Symfony.

*   **Funkcionalita:**
    *   Evidence zůstatků v různých měnách (CZK, USD, EUR, JPY, BTC atd.).
    *   **Přesnost výpočtů:** Využití knihovny `brick/money` pro eliminaci chyb plovoucí řádové čárky.
    *   **Historie kurzů:** Dynamická tabulka křížových kurzů závislá na zvolené hlavní měně.
    *   **Interaktivní grafy:** Vizualizace historie kurzů pomocí Recharts (7/14/30/90 dní).
    *   **Konvertor měn:** Okamžitý přepočet podle aktuálních kurzů.
    *   **Automatická aktualizace:** Integrace s externími API (Exchangerate.host, CurrencyFreaks) s logikou Failover (přepnutí na záložního poskytovatele při selhání).
    *   **Smart Trend Forecaster:** ML predikce kurzů na základě Python/FastAPI mikroservisu s využitím knihovny Prophet. Predikce zahrnuje interval spolehlivosti.

### 3. Polygraphy Digest (Inteligentní vyhledávání)
Agregátor novinek a produktů polygrafického průmyslu s výkonným vyhledávacím enginem.

*   **Funkcionalita:**
    *   **Agregace:** Automatický sběr dat z RSS a externích stránek.
    *   **Chytré vyhledávání:** Fulltextové vyhledávání v Elasticsearch s automatickým doplňováním a zvýrazněním výsledků.
    *   **Analytika:** Výpočet trendů aktivity publikací v reálném čase.
    *   **Rozhraní:** Moderní React rozhraní s fazetovou filtrací.

---

## 🛠 Technologický stack

### Backend
*   **Framework:** Symfony 8.0 (PHP 8.4)
*   **Microservices:** Python 3.11, FastAPI (predikce měnových kurzů)
*   **Database:** PostgreSQL 16
*   **Search Engine:** Elasticsearch 8.x
*   **Cache/Queue:** KeyDB (Redis-compatible), Redis (cache predikcí)
*   **ORM:** Doctrine ORM
*   **Queue:** Symfony Messenger
*   **Math:** `brick/money`, `brick/math` (pro finanční operace)
*   **ML/Analytics:** Prophet (predikce časových řad)

### Frontend
*   **Build Tool:** Webpack Encore
*   **Core:**
    *   *Greeting:* Bootstrap 5, Twig, Native JS.
    *   *Wallet:* **React 18**, TypeScript, Tailwind CSS, Shadcn UI.
    *   *Polygraphy:* **React 18**, TypeScript, Tailwind CSS.

### Infrastructure
*   **Docker:** Nginx, PHP-FPM, Postgres, Elasticsearch, Kibana, KeyDB, Redis, FastAPI (Python), Node.js.

---

## 🚀 Instalace a spuštění

### Předpoklady
*   Docker a Docker Compose

### Krok 1: Spuštění kontejnerů
Sestavte a spusťte prostředí:
```bash
docker compose up --build -d
```

### Krok 2: Instalace závislostí
Nainstalujte PHP a Node.js závislosti:

```bash
# PHP balíčky
docker compose exec php composer install

# Frontend balíčky
docker compose run --rm node npm install
```

### Krok 3: Konfigurace prostředí (.env.local)
Vytvořte soubor `app/.env.local` pro nastavení API klíčů a pošty. To je kriticky důležité pro funkci modulu Wallet a odesílání emailů.

```dotenv
# --- Nastavení pošty (Greeting Module) ---
MAILER_SENDER_EMAIL=hello@example.com
MAILER_SENDER_NAME="My Company"
# Režim doručení: 'file' (do složky var/mails) nebo 'smtp'
EMAIL_DELIVERY_MODE=file
# Zpoždění mezi emaily (sekundy)
EMAIL_SEQUENCE_DELAY=2

# --- API Klíče pro měnové kurzy (Wallet Module) ---
# Získejte bezplatné klíče na příslušných službách
EXCHANGERATE_HOST_KEY=your_key_here
CURRENCYFREAKS_KEY=your_key_here
```

### Krok 4: Inicializace DB a Build
Spusťte skript úplné inicializace. Vytvoří databázi, spustí migrace a **nahraje fixtures** (testovací data pro peněženku a měnové kurzy).

```bash
# Inicializace DB (Migrations + Fixtures)
docker compose exec php composer db-init

# Inicializace vyhledávacích indexů (Elasticsearch)
docker compose exec php bin/console polygraphy:search:init

# Build frontendu (Dev režim se sledováním změn)
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
Pro spuštění Unit a Integration testů (používá se samostatná testovací DB):

```bash
# Příprava testovací DB (jednou)
./bin/setup-test-db

# Spuštění testů
docker compose exec php bin/phpunit
```

### Kvalita kódu
Projekt je nastaven na přísné standardy kvality:

```bash
# Spustit úplný QA cyklus (CS Fixer + PHPStan)
docker compose exec php composer qa
```