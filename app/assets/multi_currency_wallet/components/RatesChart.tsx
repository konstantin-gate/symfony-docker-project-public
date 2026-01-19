/**
 * Komponenta grafu směnných kurzů s podporou historických dat.
 *
 * Tato komponenta zobrazuje graf vývoje směnných kurzů pro zvolenou měnu
 * za vybrané období. Využívá Recharts pro vykreslení grafu a podporuje
 * následující funkce:
 * - Výběr cílové měny (EUR, USD, atd.)
 * - Výběr období (7, 14, 30, 90 dní)
 * - Interaktivní tooltip s detaily kurzu
 * - Legenda grafu
 *
 * Připravena pro budoucí rozšíření o prognózu kurzu z Python mikroservisu.
 *
 * @module RatesChart
 * @author Smart Trend Forecaster
 */

import { useState, useEffect, useMemo } from "react";
import { TrendingUp, Loader2, Info as InfoIcon } from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
    ChartLegend,
    ChartLegendContent,
    type ChartConfig,
} from "@/components/ui/chart";
import { LineChart, Line, Area, XAxis, YAxis, CartesianGrid, ReferenceLine } from "recharts";
import { useAppConfig } from "@/context/AppConfigContext";

// ============================================================================
// TypeScript Interfaces
// ============================================================================

/**
 * Bod historických dat kurzu.
 * Reprezentuje jeden záznam v časové řadě směnného kurzu.
 */
interface HistoryDataPoint {
    /** Datum záznamu ve formátu ISO (YYYY-MM-DD) */
    date: string;
    /** Hodnota kurzu */
    rate: number;
}

/**
 * Odpověď API pro historická data.
 * Struktura JSON odpovědi z endpointu /api/multi-currency-wallet/history.
 */
interface RateHistoryResponse {
    /** Indikátor úspěchu požadavku */
    success: boolean;
    /** Kód základní měny (např. CZK) */
    base_currency: string;
    /** Množství základní měny pro kurz (např. 100) */
    base_amount?: string;
    /** Kód cílové měny (např. EUR) */
    target_currency: string;
    /** Požadovaný počet dní historie */
    days: number;
    /** Skutečný počet vrácených záznamů */
    count: number;
    /** Pole historických bodů */
    history: HistoryDataPoint[];
}

/**
 * Bod prognózy kurzu.
 * Reprezentuje jeden bod předpovědi z Python mikroservisu.
 * Připraveno pro budoucí implementaci.
 */
interface ForecastPoint {
    /** Datum prognózy ve formátu ISO */
    date: string;
    /** Predikovaná hodnota kurzu */
    value: number;
    /** Dolní hranice intervalu spolehlivosti */
    conf_low: number;
    /** Horní hranice intervalu spolehlivosti */
    conf_high: number;
}

/**
 * Odpověď API pro prognózu.
 * Struktura JSON odpovědi z Python mikroservisu.
 * Připraveno pro budoucí implementaci.
 */
interface ForecastResponse {
    /** Kód měny prognózy */
    currency: string;
    /** Časové razítko generování prognózy (ISO formát) */
    generated_at: string;
    /** Pole bodů prognózy */
    forecast: ForecastPoint[];
}

/**
 * Props komponenty RatesChart.
 * Umožňuje nastavit výchozí hodnoty při mountingu komponenty.
 */
interface RatesChartProps {
    /** Výchozí měna pro zobrazení (default: EUR) */
    initialCurrency?: string;
    /** Výchozí počet dní historie (default: 30) */
    initialDays?: number;
}

// ============================================================================
// Component
// ============================================================================

/**
 * Hlavní komponenta grafu směnných kurzů.
 *
 * Zobrazuje interaktivní čárový graf s historií kurzů vybrané měny.
 * Načítá data z API endpointu /api/multi-currency-wallet/history.
 *
 * @param props Props komponenty
 * @returns JSX element grafu
 *
 * @example
 * ```tsx
 * <RatesChart initialCurrency="EUR" initialDays={30} />
 * ```
 */
export function RatesChart({
    initialCurrency = "EUR",
    initialDays = 30
}: RatesChartProps) {
    // ========================================================================
    // Hooks & Context
    // ========================================================================

    const { translations, initialBalances, locale, walletSettings } = useAppConfig();

    // ========================================================================
    // State Management
    // ========================================================================

    /** Aktuálně vybraná měna */
    const [selectedCurrency, setSelectedCurrency] = useState<string>(initialCurrency);

    /** Aktuálně vybrané období v dnech */
    const [selectedDays, setSelectedDays] = useState<number>(initialDays);

    /** Historická data načtená z API */
    const [historyData, setHistoryData] = useState<HistoryDataPoint[]>([]);

    /** Stav načítání dat */
    const [isLoading, setIsLoading] = useState<boolean>(true);

    /** Chybová zpráva (null pokud žádná chyba) */
    const [error, setError] = useState<string | null>(null);

    /** Data prognózy z Python mikroservisu */
    const [forecastData, setForecastData] = useState<ForecastPoint[]>([]);

    /** Přepínač zobrazení prognózy */
    const [showForecast, setShowForecast] = useState<boolean>(false);

    /** Stav načítání prognózy */
    const [isForecastLoading, setIsForecastLoading] = useState<boolean>(false);

    /** Časové razítko generování prognózy */
    const [forecastGeneratedAt, setForecastGeneratedAt] = useState<string | null>(null);

    // ========================================================================
    // Computed Values
    // ========================================================================

    /**
     * Seznam dostupných měn pro výběr.
     * Generuje se z initialBalances, vylučuje základní měnu CZK.
     */
    const availableCurrencies = useMemo(() => {
        return initialBalances
            .filter(b => b.code !== walletSettings.mainCurrency)
            .map(b => ({
                code: b.code,
                name: translations[`currency_${b.code.toLowerCase()}`] || b.code,
                symbol: b.symbol
            }));
    }, [initialBalances, translations, walletSettings.mainCurrency]);

    /**
     * Možnosti období pro výběr.
     */
    const periodOptions = useMemo(() => [
        { value: 7, label: translations.chart_period_7days || "7 dní" },
        { value: 14, label: translations.chart_period_14days || "14 dní" },
        { value: 30, label: translations.chart_period_30days || "30 dní" },
        { value: 90, label: translations.chart_period_90days || "90 dní" },
    ], [translations]);

    /**
     * Konfigurace barev a popisků pro Recharts.
     * Obsahuje nastavení pro historická data i prognózu.
     */
    const chartConfig = useMemo<ChartConfig>(() => ({
        rate: {
            label: translations.chart_history_label || "Kurz (historie)",
            color: "hsl(var(--chart-1))",
        },
        forecast: {
            label: translations.chart_forecast_label || "Prognóza",
            color: "hsl(var(--chart-2))",
        },
        conf_interval: {
            label: translations.chart_confidence_label || "Interval spolehlivosti",
            color: "hsl(var(--chart-2))",
        },
    }), [translations]);

    /**
     * Sloučená data pro Recharts.
     * Kombinuje historická data s prognózou do jednoho pole.
     * Recharts vyžaduje, aby všechna data byla v jednom poli.
     */
    const chartData = useMemo(() => {
        // Krok 1: Transformace historických dat
        const historyPoints = historyData.map(point => ({
            date: point.date,
            rate: point.rate,
            forecast: null as number | null,
            conf_low: null as number | null,
            conf_high: null as number | null,
        }));

        // Krok 2: Transformace dat prognózy (pouze pokud je prognóza zobrazena)
        if (!showForecast || forecastData.length === 0) {
            return historyPoints;
        }

        const forecastPoints = forecastData.map(point => ({
            date: point.date,
            rate: null as number | null,
            forecast: point.value,
            conf_low: point.conf_low,
            conf_high: point.conf_high,
        }));

        // Krok 3: Sloučení a seřazení podle data
        const combined = [...historyPoints, ...forecastPoints];
        combined.sort((a, b) => new Date(a.date).getTime() - new Date(b.date).getTime());

        return combined;
    }, [historyData, forecastData, showForecast]);

    // ========================================================================
    // Effects
    // ========================================================================

    /**
     * Effect pro automatickou změnu vybrané měny, pokud se stane základní měnou.
     * Pokud je aktuálně vybraná měna stejná jako hlavní měna peněženky,
     * přepneme na první dostupnou měnu (nebo CZK/USD/EUR jako fallback).
     */
    useEffect(() => {
        if (selectedCurrency === walletSettings.mainCurrency) {
            // Najdeme jinou měnu, na kterou přepnout
            const newCurrency = availableCurrencies.length > 0
                ? availableCurrencies[0].code
                : (walletSettings.mainCurrency === 'CZK' ? 'EUR' : 'CZK');

            setSelectedCurrency(newCurrency);
        }
    }, [walletSettings.mainCurrency, selectedCurrency, availableCurrencies]);

    /**
     * Effect pro načtení historických dat z API.
     * Spouští se při změně vybrané měny nebo období.
     */
    useEffect(() => {
        /**
         * Načte historická data ze serveru.
         * Volá endpoint GET /api/multi-currency-wallet/history.
         */
        const fetchHistoryData = async () => {
            setIsLoading(true);
            setError(null);

            try {
                const url = `/api/multi-currency-wallet/history?currency=${selectedCurrency}&days=${selectedDays}`;
                const response = await fetch(url);

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data: RateHistoryResponse = await response.json();

                if (data.success) {
                    setHistoryData(data.history);
                } else {
                    throw new Error('Nepodařilo se načíst historická data');
                }
            } catch (err) {
                console.error('Error fetching rate history:', err);
                setError(err instanceof Error ? err.message : 'Neznámá chyba');
            } finally {
                setIsLoading(false);
            }
        };

        fetchHistoryData();
    }, [selectedCurrency, selectedDays]);

    /**
     * Effect pro načtení dat prognózy z Python API.
     * Spouští se při změně měny nebo přepnutí zobrazení prognózy.
     * Prognóza se načítá pouze pokud je přepínač showForecast zapnutý.
     */
    useEffect(() => {
        /**
         * Načte data prognózy z Python mikroservisu.
         * Volá endpoint GET /api/multi-currency-wallet/forecast/{currency}.
         * Při chybě tiše selže (prognóza je volitelná funkce).
         */
        const fetchForecastData = async () => {
            // Nenačítat prognózu, pokud není zobrazení povoleno
            if (!showForecast) {
                setForecastData([]);
                setForecastGeneratedAt(null);
                return;
            }

            setIsForecastLoading(true);

            try {
                // URL pro Symfony proxy (doporučený přístup z plánu)
                const url = `/api/multi-currency-wallet/forecast/${selectedCurrency}`;
                const response = await fetch(url);

                if (!response.ok) {
                    // Prognóza nemusí být dostupná - není to chyba
                    console.warn('Prognóza není dostupná:', response.status);
                    setForecastData([]);
                    setForecastGeneratedAt(null);
                    return;
                }

                const data: ForecastResponse = await response.json();

                if (data.forecast && Array.isArray(data.forecast)) {
                    setForecastData(data.forecast);
                    setForecastGeneratedAt(data.generated_at || null);
                } else {
                    setForecastData([]);
                    setForecastGeneratedAt(null);
                }
            } catch (err) {
                // Tiché selhání - prognóza je volitelná
                console.error('Chyba při načítání prognózy:', err);
                setForecastData([]);
                setForecastGeneratedAt(null);
            } finally {
                setIsForecastLoading(false);
            }
        };

        fetchForecastData();
    }, [selectedCurrency, showForecast]);

    // ========================================================================
    // Render Helpers
    // ========================================================================

    /**
     * Formátuje datum pro osu X grafu.
     * @param value Hodnota data ve formátu ISO
     * @returns Formátovaný řetězec (např. "1.10")
     */
    const formatXAxisDate = (value: string): string => {
        const date = new Date(value);
        return `${date.getDate()}.${date.getMonth() + 1}`;
    };

    /**
     * Formátuje hodnotu kurzu pro osu Y.
     * @param value Hodnota kurzu
     * @returns Formátovaný řetězec s 2 desetinnými místy
     */
    const formatYAxisValue = (value: number): string => {
        return value.toFixed(2);
    };

    /**
     * Formátuje popisek (label) pro tooltip.
     * @param value Datum ve formátu ISO
     * @returns Lokalizovaný formát data
     */
    const formatTooltipLabel = (value: string): string => {
        const date = new Date(value);
        return new Intl.DateTimeFormat(locale, {
            day: 'numeric',
            month: 'long',
            year: 'numeric'
        }).format(date);
    };

    // ========================================================================
    // Render
    // ========================================================================

    return (
        <Card className="bg-card border border-border" data-testid="rates-chart-container">
            <CardHeader className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4" data-testid="rates-chart-header">
                <CardTitle className="text-xl font-semibold text-foreground flex items-center gap-2" data-testid="rates-chart-title">
                    <TrendingUp className="w-5 h-5 text-accent" />
                    {translations.chart_title || "Graf kurzů měn"}
                </CardTitle>

                {/* Ovládací prvky: výběr měny, období a přepínač prognózy */}
                <div className="flex flex-wrap items-center gap-3 w-full sm:w-auto" data-testid="rates-chart-controls">
                    {/* Select pro měnu */}
                    <Select value={selectedCurrency} onValueChange={setSelectedCurrency}>
                        <SelectTrigger className="w-[120px] bg-background" data-testid="rates-chart-currency-select">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent className="bg-card border border-border">
                            {availableCurrencies.map(curr => (
                                <SelectItem key={curr.code} value={curr.code} data-testid={`rates-chart-currency-option-${curr.code}`}>
                                    {curr.symbol} {curr.code}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    {/* Select pro období */}
                    <Select
                        value={selectedDays.toString()}
                        onValueChange={(val) => setSelectedDays(parseInt(val))}
                    >
                        <SelectTrigger className="w-[120px] bg-background" data-testid="rates-chart-period-select">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent className="bg-card border border-border">
                            {periodOptions.map(opt => (
                                <SelectItem key={opt.value} value={opt.value.toString()} data-testid={`rates-chart-period-option-${opt.value}`}>
                                    {opt.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    {/* Přepínač zobrazení prognózy */}
                    <div className="flex items-center gap-2" data-testid="rates-chart-forecast-control">
                        <Switch
                            id="show-forecast"
                            checked={showForecast}
                            onCheckedChange={setShowForecast}
                            data-testid="rates-chart-forecast-toggle"
                        />
                        <label
                            htmlFor="show-forecast"
                            className="text-sm text-muted-foreground cursor-pointer whitespace-nowrap"
                            data-testid="rates-chart-forecast-label"
                        >
                            {translations.chart_show_forecast || "Zobrazit prognózu"}
                        </label>
                        {isForecastLoading && (
                            <Loader2 className="w-4 h-4 animate-spin text-muted-foreground" data-testid="rates-chart-forecast-loading" />
                        )}
                    </div>
                </div>
            </CardHeader>

            <CardContent data-testid="rates-chart-content">
                {/* Stav načítání */}
                {isLoading && (
                    <div className="h-[400px] flex items-center justify-center" data-testid="rates-chart-loading">
                        <Loader2 className="w-8 h-8 animate-spin text-accent" />
                    </div>
                )}

                {/* Chybový stav */}
                {error && (
                    <div className="h-[400px] flex items-center justify-center" data-testid="rates-chart-error">
                        <p className="text-destructive">{error}</p>
                    </div>
                )}

                {/* Graf */}
                {!isLoading && !error && chartData.length > 0 && (
                    <ChartContainer config={chartConfig} className="h-[400px] w-full" data-testid="rates-chart-graph">
                        <LineChart data={chartData} margin={{ top: 20, right: 30, left: 20, bottom: 20 }}>
                            {/* Mřížka */}
                            <CartesianGrid strokeDasharray="3 3" className="stroke-muted" />

                            {/* Osa X (data) */}
                            <XAxis
                                dataKey="date"
                                tickFormatter={formatXAxisDate}
                                className="text-xs"
                            />

                            {/* Osa Y (kurz) */}
                            <YAxis
                                domain={['auto', 'auto']}
                                tickFormatter={formatYAxisValue}
                                className="text-xs"
                            />

                            {/* Tooltip */}
                            <ChartTooltip
                                content={<ChartTooltipContent />}
                                labelFormatter={formatTooltipLabel}
                            />

                            {/* Legenda */}
                            <ChartLegend content={<ChartLegendContent />} />

                            {/* Interval spolehlivosti (poloprůhledná oblast) - MUSÍ být před čarami */}
                            {showForecast && forecastData.length > 0 && (
                                <>
                                    <Area
                                        type="monotone"
                                        dataKey="conf_high"
                                        stroke="transparent"
                                        fill="var(--color-forecast)"
                                        fillOpacity={0.15}
                                        connectNulls={false}
                                        name={chartConfig.conf_interval.label as string}
                                    />
                                    <Area
                                        type="monotone"
                                        dataKey="conf_low"
                                        stroke="transparent"
                                        fill="var(--color-forecast)"
                                        fillOpacity={0.15}
                                        connectNulls={false}
                                    />
                                </>
                            )}

                            {/* Vertikální čára "Dnes" - odděluje historii od prognózy */}
                            {showForecast && forecastData.length > 0 && (
                                <ReferenceLine
                                    x={new Date().toISOString().split('T')[0]}
                                    stroke="hsl(var(--muted-foreground))"
                                    strokeDasharray="3 3"
                                    strokeWidth={1}
                                    label={{
                                        value: translations.chart_today || "Dnes",
                                        position: "top",
                                        fill: "hsl(var(--muted-foreground))",
                                        fontSize: 12
                                    }}
                                />
                            )}

                            {/* Čára historických dat (plná) */}
                            <Line
                                type="monotone"
                                dataKey="rate"
                                stroke="var(--color-rate)"
                                strokeWidth={2}
                                dot={false}
                                activeDot={{ r: 6 }}
                                name={chartConfig.rate.label as string}
                                connectNulls={false}
                            />

                            {/* Čára prognózy (přerušovaná) */}
                            {showForecast && forecastData.length > 0 && (
                                <Line
                                    type="monotone"
                                    dataKey="forecast"
                                    stroke="var(--color-forecast)"
                                    strokeWidth={2}
                                    strokeDasharray="5 5"
                                    dot={false}
                                    activeDot={{ r: 6 }}
                                    name={chartConfig.forecast.label as string}
                                    connectNulls={false}
                                />
                            )}
                        </LineChart>
                    </ChartContainer>
                )}

                {/* Prázdný stav - žádná data */}
                {!isLoading && !error && chartData.length === 0 && (
                    <div className="h-[400px] flex items-center justify-center" data-testid="rates-chart-empty">
                        <p className="text-muted-foreground">
                            {translations.chart_no_data || "Žádná data k zobrazení"}
                        </p>
                    </div>
                )}

                {/* Informace o prognóze */}
                {showForecast && forecastData.length > 0 && (
                    <div className="mt-4 flex items-center gap-2 text-xs text-muted-foreground" data-testid="rates-chart-forecast-info">
                        <InfoIcon className="w-3 h-3" />
                        <span data-testid="rates-chart-forecast-note">
                            {translations.chart_forecast_note || "Prognóza má pouze informativní charakter"}
                        </span>
                        {forecastGeneratedAt && (
                            <span className="ml-auto" data-testid="rates-chart-forecast-generated-at">
                                {translations.chart_generated_at || "Vygenerováno"}: {new Date(forecastGeneratedAt).toLocaleString(locale)}
                            </span>
                        )}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
