import { useState } from "react";
import { ArrowRightLeft } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { useAppConfig } from "@/context/AppConfigContext";

const currencies = [
  { code: "CZK", name: "Czech Koruna" },
  { code: "EUR", name: "Euro" },
  { code: "USD", name: "US Dollar" },
  { code: "RUB", name: "Russian Ruble" },
  { code: "JPY", name: "Japanese Yen" },
  { code: "BTC", name: "Bitcoin" },
];

// Mock exchange rates (relative to USD)
const rates: Record<string, number> = {
  USD: 1,
  EUR: 0.85,
  CZK: 22.5,
  RUB: 89.5,
  JPY: 148.5,
  BTC: 0.000029,
};

export function CurrencyConverter() {
  const { translations } = useAppConfig();
  const [amount, setAmount] = useState<string>("100");
  const [fromCurrency, setFromCurrency] = useState("USD");
  const [toCurrency, setToCurrency] = useState("EUR");
  const [result, setResult] = useState<{ amount: number; rate: number } | null>(null);

  const handleSwap = () => {
    setFromCurrency(toCurrency);
    setToCurrency(fromCurrency);
    setResult(null);
  };

  const handleConvert = () => {
    const value = parseFloat(amount) || 0;
    const fromRate = rates[fromCurrency];
    const toRate = rates[toCurrency];
    const convertedAmount = (value / fromRate) * toRate;
    const exchangeRate = toRate / fromRate;
    setResult({ amount: convertedAmount, rate: exchangeRate });
  };

  const formatResult = (value: number, currency: string) => {
    if (currency === "BTC") {
      return value.toFixed(8);
    }
    return value.toLocaleString("cs-CZ", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  };

  return (
    <Card className="bg-card border border-border">
      <CardHeader>
        <CardTitle className="text-xl font-semibold text-foreground flex items-center gap-2">
          <ArrowRightLeft className="w-5 h-5 text-accent" />
          {translations.converter_title || "Currency Converter"}
        </CardTitle>
      </CardHeader>
      <CardContent>
        <div className="flex flex-col lg:flex-row items-center gap-4">
          <div className="flex-1 w-full">
            <label className="text-sm font-medium text-muted-foreground mb-2 block">{translations.converter_amount || "Amount"}</label>
            <Input
              type="number"
              value={amount}
              onChange={(e) => setAmount(e.target.value)}
              placeholder={translations.converter_enter_amount || "Enter amount"}
              className="text-lg"
            />
          </div>

          <div className="flex-1 w-full">
            <label className="text-sm font-medium text-muted-foreground mb-2 block">{translations.converter_from || "From"}</label>
            <Select value={fromCurrency} onValueChange={setFromCurrency}>
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent className="bg-card border border-border">
                {currencies.map((c) => (
                  <SelectItem key={c.code} value={c.code}>
                    {c.code} - {translations[`currency_${c.code.toLowerCase()}`] || c.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <Button
            variant="outline"
            size="icon"
            onClick={handleSwap}
            className="mt-6 shrink-0"
          >
            <ArrowRightLeft className="w-4 h-4" />
          </Button>

          <div className="flex-1 w-full">
            <label className="text-sm font-medium text-muted-foreground mb-2 block">{translations.converter_to || "To"}</label>
            <Select value={toCurrency} onValueChange={setToCurrency}>
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent className="bg-card border border-border">
                {currencies.map((c) => (
                  <SelectItem key={c.code} value={c.code}>
                    {c.code} - {translations[`currency_${c.code.toLowerCase()}`] || c.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <Button
            onClick={handleConvert}
            className="mt-6 gradient-accent text-accent-foreground border-0 px-8"
          >
            {translations.converter_convert || "Convert"}
          </Button>
        </div>

        {result && (
          <div className="mt-6 p-4 bg-secondary rounded-lg text-center animate-fade-in">
            <p className="text-2xl font-bold text-foreground">
              {parseFloat(amount).toLocaleString("cs-CZ")} {fromCurrency} = {formatResult(result.amount, toCurrency)} {toCurrency}
            </p>
            <p className="text-sm text-muted-foreground mt-2">
              {translations.converter_rate_updated || "Rate updated:"} 31 Dec 2025 01:00
            </p>
          </div>
        )}
      </CardContent>
    </Card>
  );
}
