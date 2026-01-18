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

# Získání konfigurace z prostředí
REDIS_HOST = os.getenv("REDIS_HOST", "keydb")
REDIS_PORT = int(os.getenv("REDIS_PORT", "6379"))


@asynccontextmanager
async def lifespan(app: FastAPI):
    """
    Správa životního cyklu aplikace.

    Zajišťuje inicializaci a uzavření připojení k Redis/KeyDB
    při startu a ukončení aplikace.
    """
    # Startup: Připojení k Redis/KeyDB
    app.state.redis = redis.Redis(host=REDIS_HOST, port=REDIS_PORT, decode_responses=True)
    yield
    # Shutdown: Uzavření připojení
    await app.state.redis.close()


app = FastAPI(
    title="Smart Trend Forecaster",
    description="Python mikroservis pro predikci směnných kurzů",
    version="1.0.0",
    lifespan=lifespan,
)


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
