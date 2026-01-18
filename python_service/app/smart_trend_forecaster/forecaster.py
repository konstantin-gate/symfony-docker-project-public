"""
Smart Trend Forecaster - Modul pro predikci směnných kurzů.

Tento modul obsahuje třídu CurrencyForecaster, která zajišťuje:
- Získání historických dat ze Symfony API
- Předzpracování a čištění dat
- Predikci budoucího vývoje kurzů pomocí lineární regrese
- Výpočet dovolených intervalů
"""

from typing import Optional
import httpx
import pandas as pd
import numpy as np
from sklearn.linear_model import LinearRegression
from datetime import datetime, timedelta


class CurrencyForecaster:
    """
    Třída pro predikci směnných kurzů.

    Tato třída je zodpovědná za:
    - Získání historických dat ze Symfony backendu
    - Předzpracování dat do formátu vhodného pro strojové učení
    - Trénink modelu lineární regrese
    - Generování predikcí s dovolenými intervaly

    Attributes:
        base_url (str): Základní URL pro Symfony API.
        default_days (int): Výchozí počet dnů pro predikci.
    """

    def __init__(self, base_url: str = "http://nginx"):
        """
        Inicializace třídy CurrencyForecaster.

        Nastaví základní URL pro komunikaci se Symfony API.
        Ve výchozím nastavení používá interní Docker síť.

        Args:
            base_url (str): Základní URL pro API. Výchozí je "http://nginx".
        """
        self.base_url = base_url
        self.default_days = 7

    async def fetch_history_from_symfony(
        self, currency: str, days: int = 90
    ) -> Optional[list[dict]]:
        """
        Získá historická data směnných kurzů ze Symfony API.

        Provádí HTTP GET požadavek na endpoint /api/multi-currency-wallet/history
        a vrací seznam záznamů s daty a kurzy.

        Args:
            currency (str): Kód měny (např. "EUR", "USD").
            days (int): Počet dnů historie k načtení. Výchozí je 90.

        Returns:
            Optional[list[dict]]: Seznam slovníků s klíči "date" a "rate",
                                  nebo None při chybě.

        Raises:
            httpx.HTTPStatusError: Pokud API vrátí chybový status.

        Example:
            >>> forecaster = CurrencyForecaster()
            >>> data = await forecaster.fetch_history_from_symfony("EUR", 30)
            >>> print(data)
            [{"date": "2026-01-01", "rate": 25.1}, ...]
        """
        url = f"{self.base_url}/api/multi-currency-wallet/history"
        params = {"currency": currency, "days": days}

        try:
            async with httpx.AsyncClient(timeout=30.0) as client:
                response = await client.get(url, params=params)
                response.raise_for_status()
                
                # Symfony API vrací data ve formátu {success, history: [...]}
                result = response.json()
                
                # Kontrola úspěšnosti a extrakce historie
                if result.get("success") and "history" in result:
                    return result["history"]
                else:
                    print(f"API vrátilo neúspěšný výsledek: {result}")
                    return None
                    
        except httpx.HTTPStatusError as e:
            print(f"HTTP chyba při získávání historie: {e}")
            return None
        except httpx.RequestError as e:
            print(f"Chyba při síťovém požadavku: {e}")
            return None

    def prepare_data(self, data: list[dict]) -> Optional[pd.DataFrame]:
        """
        Předzpracuje surová data do formátu pandas DataFrame.

        Provádí následující kroky:
        1. Konverze seznamu slovníků na DataFrame
        2. Parsování sloupce "date" na datetime
        3. Seřazení podle data vzestupně
        4. Odstranění duplicit a prázdných hodnot
        5. Vytvoření numerického indexu pro regresní model

        Args:
            data (list[dict]): Seznam slovníků s klíči "date" a "rate".

        Returns:
            Optional[pd.DataFrame]: Předzpracovaný DataFrame se sloupci:
                                    - date: datum jako datetime
                                    - rate: kurz jako float
                                    - day_index: numerický index dne
                                    Nebo None, pokud jsou data neplatná.

        Example:
            >>> data = [{"date": "2026-01-01", "rate": 25.1}]
            >>> df = forecaster.prepare_data(data)
            >>> print(df.columns)
            Index(['date', 'rate', 'day_index'], dtype='object')
        """
        if not data:
            return None

        try:
            # Vytvoření DataFrame
            df = pd.DataFrame(data)

            # Kontrola požadovaných sloupců
            if "date" not in df.columns or "rate" not in df.columns:
                print("Chybí požadované sloupce 'date' nebo 'rate'")
                return None

            # Konverze datumu
            df["date"] = pd.to_datetime(df["date"])

            # Konverze kurzu na float
            df["rate"] = pd.to_numeric(df["rate"], errors="coerce")

            # Odstranění prázdných hodnot
            df = df.dropna(subset=["date", "rate"])

            # Seřazení podle data
            df = df.sort_values("date").reset_index(drop=True)

            # Odstranění duplicit (ponechání posledního záznamu pro každé datum)
            df = df.drop_duplicates(subset=["date"], keep="last")

            # Vytvoření numerického indexu pro regresní model
            df["day_index"] = range(len(df))

            return df

        except Exception as e:
            print(f"Chyba při předzpracování dat: {e}")
            return None

    def predict(
        self, df: pd.DataFrame, days: int = 7
    ) -> Optional[list[dict]]:
        """
        Provede predikci budoucích kurzů pomocí lineární regrese.

        Trénuje model lineární regrese na historických datech a generuje
        predikce pro zadaný počet dnů dopředu. Vypočítává také dovolené
        intervaly na základě reziduální směrodatné odchylky.

        Algoritmus:
        1. Extrahuje features (day_index) a target (rate)
        2. Trénuje model LinearRegression
        3. Generuje predikce pro budoucí dny
        4. Počítá 95% dovolený interval pomocí 1.96 * std(residuals)

        Args:
            df (pd.DataFrame): Předzpracovaný DataFrame z metody prepare_data().
            days (int): Počet dnů pro predikci dopředu. Výchozí je 7.

        Returns:
            Optional[list[dict]]: Seznam slovníků s predikcemi:
                - date: datum predikce (YYYY-MM-DD)
                - value: predikovaná hodnota kurzu
                - conf_low: dolní hranice dovoleného intervalu
                - conf_high: horní hranice dovoleného intervalu
                Nebo None při chybě.

        Example:
            >>> predictions = forecaster.predict(df, days=7)
            >>> print(predictions[0])
            {"date": "2026-01-19", "value": 25.5, "conf_low": 25.2, "conf_high": 25.8}
        """
        if df is None or len(df) < 2:
            print("Nedostatek dat pro predikci (minimálně 2 záznamy)")
            return None

        try:
            # Příprava features a target
            X = df["day_index"].values.reshape(-1, 1)
            y = df["rate"].values

            # Trénink modelu
            model = LinearRegression()
            model.fit(X, y)

            # Výpočet reziduí pro dovolené intervaly
            predictions_train = model.predict(X)
            residuals = y - predictions_train
            residual_std = np.std(residuals)

            # 95% dovolený interval (1.96 * std)
            confidence_multiplier = 1.96

            # Generování predikcí pro budoucí dny
            last_date = df["date"].max()
            last_index = df["day_index"].max()

            forecast = []
            for i in range(1, days + 1):
                future_index = last_index + i
                future_date = last_date + timedelta(days=i)

                # Predikce hodnoty
                predicted_value = model.predict([[future_index]])[0]

                # Dovolené intervaly
                conf_low = predicted_value - confidence_multiplier * residual_std
                conf_high = predicted_value + confidence_multiplier * residual_std

                forecast.append({
                    "date": future_date.strftime("%Y-%m-%d"),
                    "value": round(float(predicted_value), 4),
                    "conf_low": round(float(conf_low), 4),
                    "conf_high": round(float(conf_high), 4),
                })

            return forecast

        except Exception as e:
            print(f"Chyba při predikci: {e}")
            return None

    async def get_forecast(
        self, currency: str, history_days: int = 90, forecast_days: int = 7
    ) -> Optional[dict]:
        """
        Kompletní pipeline pro získání predikce směnného kurzu.

        Tato metoda orchestruje celý proces:
        1. Získání historických dat ze Symfony API
        2. Předzpracování dat
        3. Trénink modelu a generování predikcí

        Args:
            currency (str): Kód měny (např. "EUR", "USD").
            history_days (int): Počet dnů historie pro trénink. Výchozí je 90.
            forecast_days (int): Počet dnů pro predikci. Výchozí je 7.

        Returns:
            Optional[dict]: Slovník s výsledky:
                - currency: kód měny
                - generated_at: timestamp generování
                - history_points: počet bodů historie použitých pro trénink
                - forecast: seznam predikcí
                Nebo None při chybě.

        Example:
            >>> result = await forecaster.get_forecast("EUR", 90, 7)
            >>> print(result["currency"])
            "EUR"
        """
        # Krok 1: Získání historických dat
        history = await self.fetch_history_from_symfony(currency, history_days)
        if not history:
            return None

        # Krok 2: Předzpracování dat
        df = self.prepare_data(history)
        if df is None:
            return None

        # Krok 3: Predikce
        forecast = self.predict(df, forecast_days)
        if not forecast:
            return None

        return {
            "currency": currency,
            "generated_at": datetime.now().isoformat(),
            "history_points": len(df),
            "forecast": forecast,
        }
