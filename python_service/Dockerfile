# Python FastAPI Mikroservis - Dockerfile
# Tento soubor definuje Docker image pro službu Smart Trend Forecaster.
# Využívá Python 3.12 slim pro minimální velikost obrazu.

FROM python:3.12-slim

# Nastavení pracovního adresáře
WORKDIR /app

# Kopírování souborů s požadavky a instalace závislostí
# Dělíme to do dvou kroků pro lepší využití Docker cache
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Kopírování zdrojového kódu aplikace
COPY app/ ./app/

# Nastavení proměnných prostředí
ENV PYTHONUNBUFFERED=1
ENV PYTHONDONTWRITEBYTECODE=1

# Otevření portu pro FastAPI
EXPOSE 8000

# Spuštění aplikace pomocí uvicorn
# --host 0.0.0.0 zajistí dostupnost z jiných kontejnerů
# --reload pro development (v produkci odebrat)
CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8000", "--reload"]
