"""
Smart Trend Forecaster - Modul pro predikci směnných kurzů.

Tento modul obsahuje veškerou logiku pro:
- Získávání historických dat ze Symfony API
- Předzpracování časových řad
- Predikci budoucího vývoje kurzů pomocí scikit-learn
- Ukládání výsledků do Redis cache
"""
