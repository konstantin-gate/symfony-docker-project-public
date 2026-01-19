"""
Smart Trend Forecaster - Modul pro predikci směnných kurzů.

Tento modul obsahuje veškerou logiku pro:
- Získávání historických dat ze Symfony API
- Předzpracování časových řad
- Predikci budoucího vývoje kurzů pomocí scikit-learn
- Ukládání výsledků do Redis cache
- REST API endpointy pro frontend
"""

from .forecaster import CurrencyForecaster
from .cache import (
    save_forecast_to_cache,
    get_forecast_from_cache,
    invalidate_forecast_cache,
    get_cache_ttl,
    FORECAST_KEY_PREFIX,
)
from .tasks import ForecastScheduler, get_or_compute_forecast
from .routes import router as forecast_router

__all__ = [
    "CurrencyForecaster",
    "ForecastScheduler",
    "save_forecast_to_cache",
    "get_forecast_from_cache",
    "invalidate_forecast_cache",
    "get_cache_ttl",
    "get_or_compute_forecast",
    "forecast_router",
    "FORECAST_KEY_PREFIX",
]

