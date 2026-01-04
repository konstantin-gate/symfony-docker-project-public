import { TrendingUp, Loader2 } from "lucide-react";
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
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    const fetchRates = async () => {
      try {
        const response = await fetch('/api/multi-currency-wallet/reference-rates');
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
        setIsLoading(false);
      }
    };

    fetchRates();
  }, []);

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

  return (
    <Card className="bg-card border border-border">
      <CardHeader>
        <CardTitle className="text-xl font-semibold text-foreground flex items-center gap-2">
          <TrendingUp className="w-5 h-5 text-accent" />
          {translations.rates_title || "Reference Exchange Rates"}
        </CardTitle>
      </CardHeader>
      <CardContent>
        <div className="rounded-lg border border-border overflow-hidden">
          <Table>
            <TableHeader>
              <TableRow className="bg-muted hover:bg-muted">
                <TableHead className="font-semibold text-foreground">{translations.rates_source || "Source Amount"}</TableHead>
                <TableHead className="font-semibold text-foreground">{translations.rates_target || "Result"}</TableHead>
                <TableHead className="font-semibold text-foreground">{translations.rates_rate || "Rate"}</TableHead>
                <TableHead className="font-semibold text-foreground">{translations.rates_updated || "Last Updated"}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {isLoading ? (
                <TableRow>
                  <TableCell colSpan={4} className="h-24 text-center">
                    <div className="flex items-center justify-center gap-2 text-muted-foreground">
                      <Loader2 className="w-5 h-5 animate-spin" />
                      <span>Loading rates...</span>
                    </div>
                  </TableCell>
                </TableRow>
              ) : rates.length > 0 ? (
                rates.map((row, index) => (
                  <TableRow key={index} className="hover:bg-secondary/50">
                    <TableCell className="font-medium text-foreground">
                      {formatAmount(row.source_amount, row.source_currency, true)} {row.source_currency}
                    </TableCell>
                    <TableCell className="text-foreground font-semibold">
                      {getSymbol(row.target_currency)} {formatAmount(row.target_amount, row.target_currency)} {row.target_currency}
                    </TableCell>
                    <TableCell className="text-foreground">
                      <span className="text-xs text-muted-foreground mr-1">1 {row.source_currency} =</span>
                      {row.rate} {row.target_currency}
                    </TableCell>
                    <TableCell className="text-muted-foreground">
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
