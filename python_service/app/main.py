"""
Smart Trend Forecaster - FastAPI Mikroservis.

Tento modul je vstupním bodem pro Python mikroservis sloužící k predikci
směnných kurzů na základě historických dat z modulu Multi-Currency Wallet.

Služba komunikuje se Symfony backendem přes HTTP API a poskytuje
analytické endpointy pro frontend.
"""

from fastapi import FastAPI
from contextlib import asynccontextmanager
import redis.asyncio as redis
import os

from app.smart_trend_forecaster import ForecastScheduler, forecast_router

# Získání konfigurace z prostředí
REDIS_HOST = os.getenv("REDIS_HOST", "keydb")
REDIS_PORT = int(os.getenv("REDIS_PORT", "6379"))

# Konfigurace plánovače prognóz
FORECAST_UPDATE_INTERVAL = int(os.getenv("FORECAST_UPDATE_INTERVAL", "3600"))
FORECAST_CURRENCIES = os.getenv("FORECAST_CURRENCIES", "EUR,USD,GBP,PLN,CHF").split(",")
ENABLE_BACKGROUND_TASKS = os.getenv("ENABLE_BACKGROUND_TASKS", "true").lower() == "true"


@asynccontextmanager
async def lifespan(app: FastAPI):
    """
    Správa životního cyklu aplikace.

    Zajišťuje inicializaci a uzavření připojení k Redis/KeyDB
    při startu a ukončení aplikace. Také spouští a zastavuje
    plánovač prognóz na pozadí.
    """
    # Startup: Připojení k Redis/KeyDB
    app.state.redis = redis.Redis(host=REDIS_HOST, port=REDIS_PORT, decode_responses=True)
    
    # Startup: Spuštění plánovače prognóz na pozadí
    if ENABLE_BACKGROUND_TASKS:
        app.state.scheduler = ForecastScheduler(
            redis_client=app.state.redis,
            currencies=FORECAST_CURRENCIES,
            update_interval=FORECAST_UPDATE_INTERVAL,
        )
        app.state.scheduler.start()
    else:
        app.state.scheduler = None
    
    yield
    
    # Shutdown: Zastavení plánovače
    if app.state.scheduler:
        await app.state.scheduler.stop()
    
    # Shutdown: Uzavření připojení
    await app.state.redis.close()


app = FastAPI(
    title="Smart Trend Forecaster",
    description="Python mikroservis pro predikci směnných kurzů",
    version="1.0.0",
    lifespan=lifespan,
)

# Registrace routeru pro analytické endpointy
app.include_router(forecast_router)


@app.get("/")
async def healthcheck() -> dict:
    """
    Healthcheck endpoint pro kontrolu dostupnosti služby.

    Vrací základní informace o stavu služby a připojení k Redis.

    Returns:
        dict: Stav služby a verze.
    """
    try:
        # Ověření připojení k Redis
        await app.state.redis.ping()
        redis_status = "connected"
    except Exception:
        redis_status = "disconnected"

    return {
        "status": "ok",
        "service": "Smart Trend Forecaster",
        "version": "1.0.0",
        "redis": redis_status,
    }


@app.get("/api/py/health")
async def api_health() -> dict:
    """
    Alternativní healthcheck endpoint pod prefixem /api/py/.

    Tento endpoint je určen pro volání přes Nginx proxy.

    Returns:
        dict: Stav služby.
    """
    return await healthcheck()
