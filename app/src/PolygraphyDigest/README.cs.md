# Modul Polygraphy Digest (Inteligentní vyhledávání a analytika)

**Polygraphy Digest** je moderní subsystém v rámci Symfony monolitu, určený pro agregaci, inteligentní vyhledávání a analýzu dat v oblasti polygrafického průmyslu. Modul kombinuje klasický server-side rendering Symfony s dynamickým rozhraním v Reactu a výkonným vyhledávacím nástrojem Elasticsearch.

## 1. Technologický stack

Modul využívá pokročilé technologie pro zajištění vysokého výkonu a škálovatelnosti:

- **Backend:** Symfony 8.0, PHP 8.4.
- **Search Engine:** Elasticsearch 8.x (fulltextové vyhledávání, fasetové filtry, agregace pro analytiku).
- **Frontend:** React 18, TypeScript, Tailwind CSS + Bootstrap 5.
- **Storage:** PostgreSQL 16 (hlavní DB), Elasticsearch (vyhledávací index), KeyDB (fronty a cache).
- **Async Processing:** Symfony Messenger + KeyDB (protokol Redis).
- **Scheduling:** Symfony Scheduler (plánování pravidelného sběru dat).
- **Localization:** i18next (podpora CS, EN, RU).

## 2. Architektonická koncepce

Modul je implementován na principu **Client-Side Rendering (CSR)** uvnitř monolitu:

1. **Serverová část:** PHP kontroler `PolygraphyController` vrací základní Twig šablonu, která slouží jako kontejner pro React aplikaci.
2. **Klientská část:** Veškerá interakce s daty probíhá přes React, který komunikuje se serverem prostřednictvím REST API (`PolygraphyApiController`).
3. **Vyhledávací vrstva:** Požadavky z API jsou směřovány do `SearchService`, který zapouzdřuje logiku interakce s Elasticsearch (zvýrazňování, agregace).
4. **Asynchronita:** Sběr a indexace dat jsou vynešeny do procesů na pozadí (Workers), což zajišťuje responzivitu rozhraní.

## 3. Spuštění a začátek práce

Pro úspěšné spuštění modulu od nuly proveďte následující kroky:

### Krok 1: Příprava infrastruktury
Ujistěte se, že jsou spuštěny všechny kontejnery (Elasticsearch, KeyDB, Kibana):
```bash
docker compose up -d --build
```

### Krok 2: Závislosti a databáze
```bash
# Instalace PHP balíčků
docker compose exec -T php composer install

# Aplikování migrací
docker compose exec -T php bin/console doctrine:migrations:migrate
```

### Krok 3: Inicializace Elasticsearch
Vytvoření indexů (`polygraphy_articles`, `polygraphy_products`) s nastaveným mapováním a analyzátory:
```bash
docker compose exec -T php bin/console polygraphy:search:init
```

### Krok 4: Sestavení Frontendu
```bash
docker compose run --rm node npm install
docker compose run --rm node npm run build
```

### Krok 5: Testovací data a zdroje
1. **Testovací indexace:** Vytvořit fiktivní článek pro ověření vyhledávání:
   ```bash
   docker compose exec -T php bin/console polygraphy:search:test-index
   ```
2. **Načtení reálných zdrojů:** Přidat RSS kanály z fixtur:
   ```bash
   docker compose exec -T php bin/console doctrine:fixtures:load --group=polygraphy --append
   ```

### Přístup k aplikaci
- **Hlavní rozhraní:** [http://localhost/polygraphy](http://localhost/polygraphy)
- **API endpointy:** `GET /api/polygraphy/articles`, `GET /api/polygraphy/products`
- **Kibana (v8.x):** [http://localhost:5601](http://localhost:5601) (pro ladění indexů)

## 4. Přehled funkcí

### 4.1. Dashboard (Nástěnka)
- **Widgety statistik:** Aktivní zdroje, trend týdne (dynamika publikací), celkový počet článků.
- **Rozdělení zdrojů:** Tabulka lídrů podle počtu publikací.

### 4.2. Vyhledávání (Search)
- **Vyhledávací pole:** Fulltextové vyhledávání s našeptávačem.
- **Fasetové filtry:** Dynamické filtry podle zdrojů (počet dokumentů) a období (Dnes, Týden, Měsíc).
- **Výsledky:** Karty se zvýrazněním textu a odkazy na originály.

### 4.3. Nastavení (Settings)
- Správa parametrů zobrazení a viditelnosti skrytých materiálů (status `HIDDEN`).

## 5. Životní cyklus dat a práce na pozadí

Proces zpracování dat je plně automatizován:

1. **Plánování:** Symfony Scheduler jednou za minutu kontroluje zdroje.
2. **Sběr (Crawling):** `CrawlerService` stahuje obsah a ukládá jej do PostgreSQL.
3. **Indexace (Indexing):** Data jsou automaticky synchronizována s Elasticsearch.
4. **Spuštění na pozadí:** Pro fungování parsování je nutné spustit workera:
   ```bash
   docker compose exec php bin/console messenger:consume polygraphy scheduler_polygraphy -vv
   ```

## 6. Ovládací příkazy

- `polygraphy:search:init` — Vytvoření struktury indexů.
- `polygraphy:search:reindex` — Synchronizace dat z DB do Elasticsearch.
- `polygraphy:search:reset` — Úplné vyčištění a znovuvytvoření vyhledávací vrstvy.
- `polygraphy:search:test-index` — Generování kontrolních dat.

## 7. Lokalizace

Modul podporuje tři jazyky (i18next): **Ruština (RU)**, **Angličtina (EN)**, **Čeština (CS)**. Přepínač je k dispozici v horním ovládacím panelu.

## 8. Nasazení (Docker)

Všechny komponenty (PostgreSQL, Elasticsearch, Redis/KeyDB) jsou nakonfigurovány v `docker-compose.yml`. Data indexů jsou ukládána v volume `elasticsearch-data`, což zabraňuje jejich ztrátě při restartu kontejnerů.
