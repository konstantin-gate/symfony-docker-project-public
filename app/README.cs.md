# Symfony Greeting App

Tato aplikace je založena na Symfony 8.0 a je určena pro správu seznamu kontaktů a rozesílání pozdravných zpráv (například k Vánocům a Novému roku). Projekt je plně dockerizován a obsahuje nástroje pro vývoj, testování a sestavení.

## Funkce

*   **Správa kontaktů:** Import seznamu e-mailových adres prostřednictvím webového rozhraní.
*   **Dashboard:** Ovládací panel (`/greeting/dashboard`) pro prohlížení kontaktů, filtrování podle data registrace a jazyka.
*   **Vícejazyčnost:** Podpora češtiny, angličtiny a ruštiny.
*   **Rozesílání:** Simulace odesílání e-mailů vybraným skupinám kontaktů.
*   **Parsování e-mailů:** Vyhrazená služba `GreetingEmailParser` pro spolehlivé zpracování seznamů adres (s podporou různých oddělovačů).
*   **Technologický stack:**
    *   **Backend:** Symfony 8.0, PHP 8.4, Doctrine ORM.
    *   **Databáze:** PostgreSQL 16.
    *   **Frontend:** Webpack Encore, Bootstrap 5, Bootstrap Icons.
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
Aplikujte migrace pro vytvoření potřebných tabulek (`greeting_contact`, `greeting_log` a další):

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
