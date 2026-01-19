# Modul Multi-Currency Wallet (Víceměnová peněženka)

**Multi-Currency Wallet** — moderní subsystém v rámci Symfony monolitu určený pro správu zůstatků ve více měnách, konverzi a sledování dynamiky kurzů. Modul kombinuje serverové renderování Symfony s dynamickým SPA rozhraním na Reactu.

## 1. Technologický stack

- **Backend:** Symfony 8.0, PHP 8.4.
- **Microservices:** Python 3.11, FastAPI (predikce kurzů).
- **Frontend:** React 18, TypeScript, shadcn/ui, Tailwind CSS, Recharts (grafy).
- **Storage:** PostgreSQL 16 (hlavní DB), KeyDB (cachování), Redis (cache mikroservisu).
- **Money Handling:** Brick/Money (přesné peněžní výpočty).
- **ML/Analytics:** Prophet (predikce časových řad).
- **Localization:** Symfony Translator (podpora CS, EN, RU).

## 2. Architektonický koncept

Modul je implementován podle principu **Client-Side Rendering (CSR)** s mikroservisní analytikou:

1. **Serverová část (Symfony):** Kontroler `WalletController` poskytuje Twig šablonu s počátečními daty, která slouží jako kontejner pro React aplikaci.
2. **Klientská část (React):** Veškerá interakce s daty probíhá přes React (react-router pro navigaci), který komunikuje se serverem přes REST API.
3. **API vrstva (Symfony):** Sada kontrolerů v `Controller/Api` pro operace se zůstatky, konverzí, kurzy a historií.
4. **Servisní vrstva (Symfony):** Obchodní logika je zapouzdřena v servisech (`CurrencyConverter`, `RateHistoryService`, `ReferenceRateService` a další).
5. **Mikroservis predikce (Python/FastAPI):** Samostatný servis pro analýzu časových řad a generování predikcí kurzů. Symfony vystupuje jako proxy (`GetForecastController`) mezi Reactem a Python servisem.

## 3. Spuštění a Začátek práce

### Krok 1: Příprava infrastruktury
```bash
docker compose up -d --build
```

### Krok 2: Závislosti a databáze
```bash
docker compose exec -T php composer install
docker compose exec -T php bin/console doctrine:migrations:migrate
```

### Krok 3: Sestavení Frontendu
```bash
docker compose run --rm node npm install
docker compose run --rm node npm run build
```

### Krok 4: Počáteční data (volitelně)
```bash
docker compose exec -T php bin/console doctrine:fixtures:load --group=wallet --append
```

### Přístup k aplikaci
- **Hlavní rozhraní:** [http://localhost/multi-currency-wallet](http://localhost/multi-currency-wallet)
- **API endpointy:**
  - `GET /api/multi-currency-wallet/balances` — Získání zůstatků.
  - `POST /api/multi-currency-wallet/convert` — Konverze měn.
  - `GET /api/multi-currency-wallet/reference-rates` — Referenční kurzy.
  - `GET /api/multi-currency-wallet/history` — Historie kurzů pro grafy.

## 4. Přehled funkcí

### 4.1. Dashboard
- **Karty zůstatků:** Zobrazení zůstatků v každé podporované měně (CZK, EUR, USD, RUB, JPY, BTC).
- **Celkový zůstatek:** Přepočet všech zůstatků do zvolené cílové měny.
- **Editace:** Inline editace zůstatku přímo na kartě.

### 4.2. Převodník (Converter)
- **Vstupní pole:** Částka, zdrojová a cílová měna.
- **Tlačítko Swap (↔):** Rychlá změna směru konverze.
- **Výsledek:** Zobrazení převedené částky a aktuálního kurzu.

### 4.3. Historie kurzů (Rates)
- **Tabulka kurzů:** Referenční kurzy s možností výběru data.
- **Interaktivní graf:** Recharts graf s výběrem měny a období (7/14/30/90 dní).
- **Smart Amount:** Graf je sestaven s ohledem na "chytré" množství (100 CZK, 1000 JPY) pro lepší čitelnost.
- **Predikce (Smart Trend Forecaster):** Integrace s Python/FastAPI mikroservisem pro ML predikci kurzů na základě knihovny Prophet. Predikce zahrnuje interval spolehlivosti.

### 4.4. Nastavení (Settings)
- **Hlavní měna:** Výběr hlavní měny peněženky.
- **Automatická aktualizace:** Zapnutí/vypnutí automatického načítání aktuálních kurzů.

## 5. Aktualizace kurzů

Měnové kurzy se načítají z externích poskytovatelů (`exchangerate.host`, `currencyfreaks.com`) s logikou failover:

1. **Ruční aktualizace:** Tlačítko "Aktualizovat kurzy" v záhlaví aplikace.
2. **Automatická aktualizace:** Při načtení stránky, pokud uplynula více než hodina od poslední aktualizace a nastalo 09:00 pražského času.
3. **Throttling:** Maximálně jedna aktualizace za hodinu pro prevenci zneužití.

## 6. Klíčové servisy

| Servis                    | Účel                                                            |
|---------------------------|-----------------------------------------------------------------|
| `CurrencyConverter`       | Konverze měn s podporou křížových kurzů přes USD.               |
| `ReferenceRateService`    | Generování referenčních kurzů s "chytrými" částkami.            |
| `RateHistoryService`      | Získání historie kurzů pro analytiku a grafy.                   |
| `RateUpdateService`       | Načítání kurzů z externích API (konfigurovatelný failover).     |
| `BalanceNormalizerService`| Normalizace zůstatků mezi entitami a DTO.                       |

### Python Microservice (FastAPI)

| Komponenta                | Účel                                                            |
|---------------------------|-----------------------------------------------------------------|
| `CurrencyForecaster`      | ML model na základě Prophet pro predikci kurzů.                 |
| `GET /forecast/{currency}`| API endpoint pro získání predikce s cachováním v Redis.         |

## 7. Lokalizace

Modul podporuje tři jazyky: **Čeština (CS)**, **English (EN)**, **Русский (RU)**. Přepínač je dostupný v postranním menu. Soubory překladů jsou umístěny v `app/translations/MultiCurrencyWallet/`.

## 8. Nasazení (Docker)

Všechny komponenty jsou konfigurovány v `docker-compose.yml`:
- **PHP (Symfony):** Hlavní backend.
- **PostgreSQL:** Úložiště dat.
- **KeyDB:** Cachování na straně Symfony.
- **FastAPI (Python):** Mikroservis predikce (`fastapi` kontejner).
- **Redis:** Cache predikcí pro Python servis.

Modul může fungovat v základním režimu (bez predikcí) bez Python servisu.
