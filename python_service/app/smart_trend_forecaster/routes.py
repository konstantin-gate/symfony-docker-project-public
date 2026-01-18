"""
Smart Trend Forecaster - API Routes pro prognózy.

Tento modul obsahuje FastAPI endpointy pro získávání predikcí
směnných kurzů. Implementuje cache-first strategii a poskytuje
RESTful rozhraní pro frontend aplikace.
"""

from fastapi import APIRouter, Request, HTTPException, Query, BackgroundTasks
from typing import Optional
from datetime import datetime

from .tasks import get_or_compute_forecast
from .cache import get_forecast_from_cache, get_cache_ttl


# Vytvoření routeru pro analytické endpointy
router = APIRouter(
    prefix="/wallet/analytics",
    tags=["analytics", "forecast"],
)


@router.get("/forecast/{currency}")
async def get_forecast(
    request: Request,
    currency: str,
    background_tasks: BackgroundTasks,
    days: int = Query(default=7, ge=1, le=30, description="Počet dnů pro predikci"),
    force_refresh: bool = Query(default=False, description="Vynutit přepočet prognózy"),
) -> dict:
    """
    Získá prognózu směnného kurzu pro zadanou měnu.

    Tento endpoint implementuje strategii cache-first:
    1. Pokusí se načíst prognózu z Redis cache
    2. Pokud není v cache, spustí výpočet na pozadí a vrátí status "processing"
    3. Při force_refresh=True vždy přepočítá prognózu

    Prognóza obsahuje predikované hodnoty kurzu s uvedením
    dovolených intervalů (confidence intervals).

    Args:
        request (Request): FastAPI request objekt pro přístup k app.state.
        currency (str): ISO kód měny (např. "EUR", "USD").
        background_tasks (BackgroundTasks): FastAPI background tasks pro async výpočty.
        days (int): Počet dnů pro predikci (1-30). Výchozí: 7.
        force_refresh (bool): Pokud True, vynutí přepočet i když je v cache.

    Returns:
        dict: Slovník s prognózou v následujícím formátu:
            - status: "ready" nebo "processing"
            - currency: kód měny
            - generated_at: timestamp generování prognózy
            - forecast: seznam predikcí (pokud status="ready")
            - message: informační zpráva (pokud status="processing")

    Raises:
        HTTPException: 400 pokud je měna neplatná nebo není podporována.

    Example:
        GET /wallet/analytics/forecast/EUR?days=7

        Response:
        {
            "status": "ready",
            "currency": "EUR",
            "generated_at": "2026-01-18T10:00:00",
            "history_points": 90,
            "forecast": [
                {"date": "2026-01-19", "value": 25.5, "conf_low": 25.2, "conf_high": 25.8},
                ...
            ]
        }
    """
    # Normalizace kódu měny
    currency = currency.upper().strip()
    
    # Validace kódu měny (základní kontrola)
    if len(currency) != 3 or not currency.isalpha():
        raise HTTPException(
            status_code=400,
            detail=f"Neplatný kód měny: {currency}. Očekává se 3-písmenný ISO kód.",
        )
    
    # Získání Redis klienta z app state
    redis_client = request.app.state.redis
    
    # Pokus o získání prognózy (cache-first strategie)
    forecast = await get_or_compute_forecast(
        redis_client=redis_client,
        currency=currency,
        force_refresh=force_refresh,
    )
    
    if forecast:
        # Prognóza je k dispozici
        return {
            "status": "ready",
            "currency": forecast["currency"],
            "generated_at": forecast["generated_at"],
            "history_points": forecast.get("history_points", 0),
            "from_cache": forecast.get("from_cache", False),
            "cached_at": forecast.get("cached_at"),
            "forecast": forecast["forecast"][:days],  # Omezení na požadovaný počet dnů
        }
    else:
        # Prognóza není k dispozici - vrátíme status processing
        return {
            "status": "processing",
            "currency": currency,
            "message": (
                f"Prognóza pro {currency} není momentálně k dispozici. "
                "Systém ji právě počítá. Zkuste to prosím za chvíli."
            ),
            "retry_after_seconds": 30,
        }


@router.get("/forecast/{currency}/status")
async def get_forecast_status(
    request: Request,
    currency: str,
) -> dict:
    """
    Zjistí stav prognózy pro zadanou měnu.

    Tento endpoint je užitečný pro polling - umožňuje klientovi
    zjistit, zda je prognóza již k dispozici v cache, jaký je
    její TTL a kdy byla vygenerována.

    Args:
        request (Request): FastAPI request objekt.
        currency (str): ISO kód měny.

    Returns:
        dict: Informace o stavu prognózy:
            - available: True/False
            - currency: kód měny
            - ttl_seconds: zbývající čas platnosti cache
            - generated_at: timestamp generování (pokud k dispozici)

    Example:
        GET /wallet/analytics/forecast/EUR/status

        Response:
        {
            "available": true,
            "currency": "EUR",
            "ttl_seconds": 3200,
            "generated_at": "2026-01-18T10:00:00"
        }
    """
    currency = currency.upper().strip()
    redis_client = request.app.state.redis
    
    # Kontrola existence v cache
    forecast = await get_forecast_from_cache(redis_client, currency)
    ttl = await get_cache_ttl(redis_client, currency)
    
    if forecast:
        return {
            "available": True,
            "currency": currency,
            "ttl_seconds": ttl if ttl > 0 else 0,
            "generated_at": forecast.get("generated_at"),
            "cached_at": forecast.get("cached_at"),
            "history_points": forecast.get("history_points", 0),
        }
    else:
        return {
            "available": False,
            "currency": currency,
            "ttl_seconds": 0,
            "message": "Prognóza není v cache. Zavolejte hlavní endpoint pro její vygenerování.",
        }


@router.get("/currencies")
async def list_supported_currencies(request: Request) -> dict:
    """
    Vrátí seznam měn, pro které jsou k dispozici prognózy.

    Tento endpoint vrací seznam měn nakonfigurovaných v plánovači
    a informaci o tom, které z nich mají aktuální prognózu v cache.

    Args:
        request (Request): FastAPI request objekt.

    Returns:
        dict: Seznam měn s informacemi o dostupnosti prognóz.

    Example:
        GET /wallet/analytics/currencies

        Response:
        {
            "currencies": [
                {"code": "EUR", "has_forecast": true, "ttl_seconds": 3200},
                {"code": "USD", "has_forecast": true, "ttl_seconds": 3100},
                {"code": "GBP", "has_forecast": false, "ttl_seconds": 0}
            ]
        }
    """
    redis_client = request.app.state.redis
    scheduler = request.app.state.scheduler
    
    # Získání seznamu měn z plánovače
    currencies = scheduler.currencies if scheduler else ["EUR", "USD"]
    
    result = []
    for currency in currencies:
        forecast = await get_forecast_from_cache(redis_client, currency)
        ttl = await get_cache_ttl(redis_client, currency)
        
        result.append({
            "code": currency,
            "has_forecast": forecast is not None,
            "ttl_seconds": ttl if ttl > 0 else 0,
        })
    
    return {
        "currencies": result,
        "timestamp": datetime.now().isoformat(),
    }
