"""
Smart Trend Forecaster - Modul pro správu úloh na pozadí.

Tento modul obsahuje logiku pro periodické přepočítávání prognóz
směnných kurzů a jejich ukládání do cache. Úlohy běží asynchronně
na pozadí a automaticky obnovují prognózy v pravidelných intervalech.
"""

import asyncio
from typing import Optional
from datetime import datetime
import redis.asyncio as redis

from .forecaster import CurrencyForecaster
from .cache import save_forecast_to_cache, get_forecast_from_cache


# Výchozí interval pro aktualizaci prognóz (1 hodina v sekundách)
DEFAULT_UPDATE_INTERVAL = 3600

# Seznam měn pro automatické prognózování
DEFAULT_CURRENCIES = ["EUR", "USD", "GBP", "PLN", "CHF"]


class ForecastScheduler:
    """
    Plánovač pro automatické aktualizace prognóz.

    Tato třída spravuje periodické úlohy na pozadí, které automaticky
    přepočítávají prognózy směnných kurzů a ukládají je do Redis cache.
    Zajišťuje, že prognózy jsou vždy aktuální bez nutnosti čekat
    na jejich výpočet při požadavku uživatele.

    Attributes:
        redis_client (redis.Redis): Asynchronní Redis klient.
        forecaster (CurrencyForecaster): Instance třídy pro prognózování.
        currencies (list[str]): Seznam měn ke sledování.
        update_interval (int): Interval aktualizace v sekundách.
        _task (asyncio.Task): Reference na běžící úlohu na pozadí.
        _running (bool): Příznak, zda plánovač běží.
    """

    def __init__(
        self,
        redis_client: redis.Redis,
        currencies: Optional[list[str]] = None,
        update_interval: int = DEFAULT_UPDATE_INTERVAL,
    ):
        """
        Inicializace plánovače prognóz.

        Vytvoří instanci plánovače s daným Redis klientem a konfigurací.
        Plánovač není automaticky spuštěn - je třeba zavolat metodu start().

        Args:
            redis_client (redis.Redis): Asynchronní Redis klient pro cache.
            currencies (Optional[list[str]]): Seznam kódů měn ke sledování.
                                              Výchozí: ["EUR", "USD", "GBP", "PLN", "CHF"]
            update_interval (int): Interval mezi aktualizacemi v sekundách.
                                   Výchozí: 3600 (1 hodina).
        """
        self.redis_client = redis_client
        self.forecaster = CurrencyForecaster()
        self.currencies = currencies or DEFAULT_CURRENCIES
        self.update_interval = update_interval
        self._task: Optional[asyncio.Task] = None
        self._running = False

    async def update_forecast_for_currency(self, currency: str) -> bool:
        """
        Aktualizuje prognózu pro jednu měnu.

        Provede kompletní výpočet prognózy pro zadanou měnu
        a uloží výsledek do Redis cache.

        Args:
            currency (str): Kód měny (např. "EUR").

        Returns:
            bool: True pokud byla aktualizace úspěšná, False při chybě.

        Example:
            >>> success = await scheduler.update_forecast_for_currency("EUR")
            >>> print(f"EUR aktualizace: {'OK' if success else 'FAILED'}")
        """
        try:
            print(f"[{datetime.now().isoformat()}] Aktualizuji prognózu pro {currency}...")
            
            # Výpočet prognózy
            forecast = await self.forecaster.get_forecast(
                currency=currency,
                history_days=90,
                forecast_days=7,
            )
            
            if forecast is None:
                print(f"  ⚠ Prognóza pro {currency} se nepodařila vypočítat")
                return False
            
            # Uložení do cache
            success = await save_forecast_to_cache(
                self.redis_client,
                currency,
                forecast,
                ttl=self.update_interval,
            )
            
            if success:
                print(f"  ✓ Prognóza pro {currency} uložena do cache")
            else:
                print(f"  ✗ Nepodařilo se uložit prognózu pro {currency}")
            
            return success
            
        except Exception as e:
            print(f"  ✗ Chyba při aktualizaci {currency}: {e}")
            return False

    async def update_all_forecasts(self) -> dict[str, bool]:
        """
        Aktualizuje prognózy pro všechny sledované měny.

        Iteruje přes všechny nakonfigurované měny a aktualizuje
        jejich prognózy. Vrací souhrn úspěšnosti pro každou měnu.

        Returns:
            dict[str, bool]: Slovník s měnami jako klíči a bool hodnotami
                              indikujícími úspěšnost aktualizace.

        Example:
            >>> results = await scheduler.update_all_forecasts()
            >>> print(results)
            {"EUR": True, "USD": True, "GBP": False}
        """
        results = {}
        print(f"\n[{datetime.now().isoformat()}] === Zahajuji hromadnou aktualizaci prognóz ===")
        
        for currency in self.currencies:
            results[currency] = await self.update_forecast_for_currency(currency)
            # Malá pauza mezi měnami pro snížení zátěže API
            await asyncio.sleep(1)
        
        successful = sum(1 for v in results.values() if v)
        print(f"[{datetime.now().isoformat()}] === Aktualizace dokončena: {successful}/{len(self.currencies)} úspěšných ===\n")
        
        return results

    async def _background_loop(self) -> None:
        """
        Hlavní smyčka na pozadí pro periodické aktualizace.

        Běží nekonečně a v pravidelných intervalech spouští
        aktualizaci všech prognóz. Tato metoda by neměla být
        volána přímo - použijte metodu start().
        """
        print(f"[{datetime.now().isoformat()}] ForecastScheduler: Spuštěna smyčka na pozadí")
        print(f"  Interval: {self.update_interval}s, Měny: {self.currencies}")
        
        # První aktualizace ihned po startu
        await self.update_all_forecasts()
        
        while self._running:
            try:
                # Čekání na další interval
                await asyncio.sleep(self.update_interval)
                
                if self._running:
                    await self.update_all_forecasts()
                    
            except asyncio.CancelledError:
                print(f"[{datetime.now().isoformat()}] ForecastScheduler: Smyčka zrušena")
                break
            except Exception as e:
                print(f"[{datetime.now().isoformat()}] ForecastScheduler: Neočekávaná chyba: {e}")
                # Pokračovat v běhu i po chybě
                await asyncio.sleep(60)

    def start(self) -> asyncio.Task:
        """
        Spustí plánovač na pozadí.

        Vytvoří asyncio task, který běží na pozadí a periodicky
        aktualizuje prognózy. Task je automaticky zaregistrován
        v aktuálním event loopu.

        Returns:
            asyncio.Task: Reference na vytvořený task.

        Raises:
            RuntimeError: Pokud je plánovač již spuštěn.

        Example:
            >>> task = scheduler.start()
            >>> print(f"Plánovač běží: {not task.done()}")
            Plánovač běží: True
        """
        if self._running:
            raise RuntimeError("ForecastScheduler je již spuštěn")
        
        self._running = True
        self._task = asyncio.create_task(self._background_loop())
        return self._task

    async def stop(self) -> None:
        """
        Zastaví plánovač.

        Bezpečně ukončí běžící task na pozadí a vyčká na jeho dokončení.

        Example:
            >>> await scheduler.stop()
            >>> print("Plánovač zastaven")
        """
        if not self._running:
            return
        
        print(f"[{datetime.now().isoformat()}] ForecastScheduler: Zastavuji...")
        self._running = False
        
        if self._task:
            self._task.cancel()
            try:
                await self._task
            except asyncio.CancelledError:
                pass
            self._task = None
        
        print(f"[{datetime.now().isoformat()}] ForecastScheduler: Zastaven")

    @property
    def is_running(self) -> bool:
        """
        Vrátí stav plánovače.

        Returns:
            bool: True pokud plánovač běží, False jinak.
        """
        return self._running and self._task is not None and not self._task.done()


async def get_or_compute_forecast(
    redis_client: redis.Redis,
    currency: str,
    force_refresh: bool = False,
) -> Optional[dict]:
    """
    Získá prognózu z cache nebo ji vypočítá na vyžádání.

    Tato funkce implementuje strategii "cache-first":
    1. Pokusí se načíst prognózu z cache
    2. Pokud není v cache (nebo force_refresh=True), vypočítá novou
    3. Uloží novou prognózu do cache

    Tato metoda je vhodná pro synchronní požadavky, kdy je třeba
    vrátit prognózu okamžitě bez čekání na background task.

    Args:
        redis_client (redis.Redis): Asynchronní Redis klient.
        currency (str): Kód měny (např. "EUR").
        force_refresh (bool): Pokud True, vždy přepočítá prognózu.
                              Výchozí: False.

    Returns:
        Optional[dict]: Slovník s prognózou, nebo None při chybě.

    Example:
        >>> forecast = await get_or_compute_forecast(redis, "EUR")
        >>> print(forecast["currency"])
        EUR
    """
    # Pokus o načtení z cache (pokud není vynucen refresh)
    if not force_refresh:
        cached = await get_forecast_from_cache(redis_client, currency)
        if cached:
            cached["from_cache"] = True
            return cached
    
    # Výpočet nové prognózy
    forecaster = CurrencyForecaster()
    forecast = await forecaster.get_forecast(currency, history_days=90, forecast_days=7)
    
    if forecast:
        forecast["from_cache"] = False
        # Uložení do cache pro příští požadavky
        await save_forecast_to_cache(redis_client, currency, forecast)
    
    return forecast
