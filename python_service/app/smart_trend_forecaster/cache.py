"""
Smart Trend Forecaster - Modul pro práci s Redis cache.

Tento modul poskytuje funkce pro ukládání a čtení predikcí
směnných kurzů z Redis/KeyDB cache. Zajišťuje rychlý přístup
k předpočítaným prognózám bez nutnosti opakovaného výpočtu.
"""

import json
from typing import Optional
from datetime import datetime
import redis.asyncio as redis


# Klíčový prefix pro prognózy v cache
FORECAST_KEY_PREFIX = "wallet:forecast:"

# Výchozí TTL pro cache (1 hodina v sekundách)
DEFAULT_TTL = 3600


async def save_forecast_to_cache(
    redis_client: redis.Redis,
    currency: str,
    forecast_data: dict,
    ttl: int = DEFAULT_TTL,
) -> bool:
    """
    Uloží prognózu směnného kurzu do Redis cache.

    Serializuje data prognózy do JSON formátu a uloží je
    do Redis s nastaveným TTL (Time To Live). Po vypršení TTL
    bude záznam automaticky odstraněn.

    Args:
        redis_client (redis.Redis): Asynchronní Redis klient.
        currency (str): Kód měny (např. "EUR", "USD").
        forecast_data (dict): Slovník s daty prognózy obsahující:
            - currency: kód měny
            - generated_at: timestamp generování
            - history_points: počet bodů historie
            - forecast: seznam predikcí
        ttl (int): Doba platnosti cache v sekundách. Výchozí je 3600 (1 hodina).

    Returns:
        bool: True pokud bylo uložení úspěšné, False při chybě.

    Example:
        >>> success = await save_forecast_to_cache(redis, "EUR", forecast_data)
        >>> print(success)
        True
    """
    try:
        key = f"{FORECAST_KEY_PREFIX}{currency.upper()}"
        
        # Přidání timestamp uložení do cache
        forecast_data["cached_at"] = datetime.now().isoformat()
        
        # Serializace a uložení
        json_data = json.dumps(forecast_data, ensure_ascii=False)
        await redis_client.setex(key, ttl, json_data)
        
        return True
    except Exception as e:
        print(f"Chyba při ukládání prognózy do cache: {e}")
        return False


async def get_forecast_from_cache(
    redis_client: redis.Redis,
    currency: str,
) -> Optional[dict]:
    """
    Načte prognózu směnného kurzu z Redis cache.

    Pokusí se načíst a deserializovat prognózu pro zadanou měnu.
    Pokud záznam neexistuje nebo vypršel, vrátí None.

    Args:
        redis_client (redis.Redis): Asynchronní Redis klient.
        currency (str): Kód měny (např. "EUR", "USD").

    Returns:
        Optional[dict]: Slovník s daty prognózy, nebo None pokud
                        prognóza není v cache nebo vypršela.

    Example:
        >>> forecast = await get_forecast_from_cache(redis, "EUR")
        >>> if forecast:
        ...     print(forecast["currency"])
        EUR
    """
    try:
        key = f"{FORECAST_KEY_PREFIX}{currency.upper()}"
        
        # Načtení z cache
        json_data = await redis_client.get(key)
        
        if json_data is None:
            return None
        
        # Deserializace
        return json.loads(json_data)
    except Exception as e:
        print(f"Chyba při čtení prognózy z cache: {e}")
        return None


async def invalidate_forecast_cache(
    redis_client: redis.Redis,
    currency: Optional[str] = None,
) -> int:
    """
    Zneplatní (smaže) prognózy z cache.

    Může smazat prognózu pro konkrétní měnu, nebo všechny prognózy
    pokud není měna specifikována.

    Args:
        redis_client (redis.Redis): Asynchronní Redis klient.
        currency (Optional[str]): Kód měny k zneplatnění.
                                  Pokud None, smaže všechny prognózy.

    Returns:
        int: Počet smazaných klíčů.

    Example:
        >>> deleted = await invalidate_forecast_cache(redis, "EUR")
        >>> print(f"Smazáno {deleted} klíčů")
        Smazáno 1 klíčů
    """
    try:
        if currency:
            # Smazání konkrétní měny
            key = f"{FORECAST_KEY_PREFIX}{currency.upper()}"
            deleted = await redis_client.delete(key)
            return deleted
        else:
            # Smazání všech prognóz (pattern matching)
            pattern = f"{FORECAST_KEY_PREFIX}*"
            keys = []
            async for key in redis_client.scan_iter(pattern):
                keys.append(key)
            
            if keys:
                deleted = await redis_client.delete(*keys)
                return deleted
            return 0
    except Exception as e:
        print(f"Chyba při mazání cache: {e}")
        return 0


async def get_cache_ttl(
    redis_client: redis.Redis,
    currency: str,
) -> int:
    """
    Vrátí zbývající TTL (Time To Live) pro prognózu v cache.

    Užitečné pro zjištění, jak dlouho je prognóza ještě platná.

    Args:
        redis_client (redis.Redis): Asynchronní Redis klient.
        currency (str): Kód měny.

    Returns:
        int: Zbývající čas v sekundách. -2 pokud klíč neexistuje,
             -1 pokud klíč nemá TTL (nemělo by nastat).

    Example:
        >>> ttl = await get_cache_ttl(redis, "EUR")
        >>> print(f"Prognóza vyprší za {ttl} sekund")
    """
    try:
        key = f"{FORECAST_KEY_PREFIX}{currency.upper()}"
        return await redis_client.ttl(key)
    except Exception as e:
        print(f"Chyba při čtení TTL: {e}")
        return -2
