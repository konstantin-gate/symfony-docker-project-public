import { TrendingUp, Loader2, Calendar } from "lucide-react";
import { useEffect, useState } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { useAppConfig } from "@/context/AppConfigContext";

interface ReferenceRate {
  source_amount: string;
  source_currency: string;
  target_amount: string;
  target_currency: string;
  rate: string;
  updated_at: string;
}

export function RatesHistoryTable() {
  const { translations, initialBalances, locale } = useAppConfig();
  const [rates, setRates] = useState<ReferenceRate[]>([]);
  const [availableDates, setAvailableDates] = useState<string[]>([]);
  const [selectedDate, setSelectedDate] = useState<string>("latest");
  const [isLoading, setIsLoading] = useState(true);
  const [isRatesLoading, setIsRatesLoading] = useState(false);

  // Načtení dostupných dat při montování
  useEffect(() => {
    const fetchDates = async () => {
      try {
        const response = await fetch('/api/multi-currency-wallet/available-dates');
        const data = await response.json();
        if (data.success && data.dates.length > 0) {
          setAvailableDates(data.dates);
        }
      } catch (error) {
        console.error('Error fetching available dates:', error);
      } finally {
        setIsLoading(false);
      }
    };

    fetchDates();
  }, []);

  // Načtení kurzů při změně vybraného data
  useEffect(() => {
    const fetchRates = async () => {
      setIsRatesLoading(true);
      try {
        const url = selectedDate && selectedDate !== "latest"
          ? `/api/multi-currency-wallet/reference-rates?date=${selectedDate}`
          : '/api/multi-currency-wallet/reference-rates';

        const response = await fetch(url);
        if (!response.ok) {
          throw new Error('Failed to fetch rates');
        }
        const data = await response.json();
        if (data.success) {
          setRates(data.rates);
        }
      } catch (error) {
        console.error('Error fetching reference rates:', error);
      } finally {
        setIsRatesLoading(false);
      }
    };

    if (!isLoading) {
      fetchRates();
    }
  }, [selectedDate, isLoading]);

  const formatAmount = (amount: string, currencyCode: string, isSource: boolean = false) => {
    const value = parseFloat(amount);

    // Pro zdrojovou částku: pokud je to celé číslo, zobrazíme ho bez desetinných míst
    if (isSource && Number.isInteger(value)) {
      return value.toString();
    }

    const meta = initialBalances.find(b => b.code === currencyCode);
    const decimals = meta ? meta.decimals : 2;

    return value.toLocaleString("cs-CZ", {
      minimumFractionDigits: decimals,
      maximumFractionDigits: decimals
    });
  };

  const getSymbol = (currencyCode: string) => {
    return initialBalances.find(b => b.code === currencyCode)?.symbol || "";
  };

  const formatDate = (dateString: string) => {
    try {
      const date = new Date(dateString);
      return new Intl.DateTimeFormat(locale, {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      }).format(date);
    } catch (e) {
      return dateString;
    }
  };

  const formatShortDate = (dateString: string) => {
    if (dateString === "latest") return translations.rates_latest || "Latest";
    try {
      const date = new Date(dateString);
      return new Intl.DateTimeFormat(locale, {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
      }).format(date);
    } catch (e) {
      return dateString;
    }
  };

  if (isLoading) {
    return (
      <Card className="bg-card border border-border">
        <CardContent className="h-64 flex items-center justify-center">
          <Loader2 className="w-8 h-8 animate-spin text-accent" />
        </CardContent>
      </Card>
    );
  }

  return (
    <Card className="bg-card border border-border" data-testid="rates-table-container">
      <CardHeader className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <CardTitle className="text-xl font-semibold text-foreground flex items-center gap-2">
          <TrendingUp className="w-5 h-5 text-accent" />
          {translations.rates_title || "Reference Exchange Rates"}
        </CardTitle>

        <div className="flex items-center gap-2 w-full sm:w-auto">
          <Calendar className="w-4 h-4 text-muted-foreground" />
          <Select value={selectedDate} onValueChange={setSelectedDate}>
            <SelectTrigger className="w-full sm:w-[200px] bg-background" data-testid="rates-table-date-select">
              <SelectValue />
            </SelectTrigger>
            <SelectContent className="bg-card border border-border">
              <SelectItem value="latest">{translations.rates_latest || "Latest"}</SelectItem>
              {availableDates.map((date) => (
                <SelectItem key={date} value={date}>
                  {formatShortDate(date)}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      </CardHeader>
      <CardContent>
        <div className="rounded-lg border border-border overflow-hidden relative">
          {isRatesLoading && (
            <div className="absolute inset-0 bg-background/50 backdrop-blur-[1px] flex items-center justify-center z-10">
              <Loader2 className="w-8 h-8 animate-spin text-accent" />
            </div>
          )}
          <Table data-testid="rates-table">
            <TableHeader>
              <TableRow className="bg-muted hover:bg-muted">
                <TableHead className="h-10 py-2 px-4 font-semibold text-foreground" data-testid="rates-table-header-source">{translations.rates_source || "Source Amount"}</TableHead>
                <TableHead className="h-10 py-2 px-4 font-semibold text-foreground" data-testid="rates-table-header-target">{translations.rates_target || "Result"}</TableHead>
                <TableHead className="h-10 py-2 px-4 font-semibold text-foreground" data-testid="rates-table-header-rate">{translations.rates_rate || "Rate"}</TableHead>
                <TableHead className="h-10 py-2 px-4 font-semibold text-foreground" data-testid="rates-table-header-updated">{translations.rates_updated || "Last Updated"}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {rates.length > 0 ? (
                rates.map((row, index) => (
                  <TableRow key={index} className="hover:bg-secondary/50" data-testid={`rates-table-row-${index}`}>
                    <TableCell className="py-2 px-4 font-medium text-foreground">
                      {formatAmount(row.source_amount, row.source_currency, true)} {row.source_currency}
                    </TableCell>
                    <TableCell className="py-2 px-4 text-foreground font-semibold">
                      {getSymbol(row.target_currency)} {formatAmount(row.target_amount, row.target_currency)} {row.target_currency}
                    </TableCell>
                    <TableCell className="py-2 px-4 text-foreground">
                      <span className="text-xs text-muted-foreground mr-1">1 {row.source_currency} =</span>
                      {row.rate} {row.target_currency}
                    </TableCell>
                    <TableCell className="py-2 px-4 text-muted-foreground">
                      {formatDate(row.updated_at)}
                    </TableCell>
                  </TableRow>
                ))
              ) : (
                <TableRow>
                  <TableCell colSpan={4} className="h-24 text-center text-muted-foreground">
                    No data available
                  </TableCell>
                </TableRow>
              )}
            </TableBody>
          </Table>
        </div>
      </CardContent>
    </Card>
  );
}