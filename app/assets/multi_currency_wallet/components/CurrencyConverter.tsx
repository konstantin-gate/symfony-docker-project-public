import { useState } from "react";
import { ArrowRightLeft, RefreshCw } from "lucide-react";
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
import { toast } from "sonner";

export function CurrencyConverter() {
  const { translations, initialBalances } = useAppConfig();
  const [amount, setAmount] = useState<string>("100");
  const [fromCurrency, setFromCurrency] = useState("USD");
  const [toCurrency, setToCurrency] = useState("EUR");
  const [isConverting, setIsConverting] = useState(false);
  const [result, setResult] = useState<{ amount: number; rate: number; updatedAt?: string } | null>(null);

  const handleSwap = () => {
    setFromCurrency(toCurrency);
    setToCurrency(fromCurrency);
    setResult(null);
  };

  const handleConvert = async () => {
    setIsConverting(true);
    try {
      const response = await fetch('/api/multi-currency-wallet/convert', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          amount: amount,
          from: fromCurrency,
          to: toCurrency
        })
      });

      if (!response.ok) {
        throw new Error('Conversion failed');
      }

      const data = await response.json();
      setResult({
        amount: parseFloat(data.amount),
        rate: parseFloat(data.rate),
        updatedAt: data.updatedAt
      });
    } catch (error) {
      console.error(error);
      toast.error(translations.converter_error || "Failed to convert currency");
    } finally {
      setIsConverting(false);
    }
  };

  const formatResult = (value: number, currency: string) => {
    const balanceInfo = initialBalances.find(b => b.code === currency);
    const decimals = balanceInfo?.decimals ?? 2;
    
    return value.toLocaleString("cs-CZ", { 
      minimumFractionDigits: decimals, 
      maximumFractionDigits: decimals 
    });
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
              onChange={(e) => {
                setAmount(e.target.value);
                setResult(null);
              }}
              placeholder={translations.converter_enter_amount || "Enter amount"}
              className="text-lg"
            />
          </div>

          <div className="flex-1 w-full">
            <label className="text-sm font-medium text-muted-foreground mb-2 block">{translations.converter_from || "From"}</label>
            <Select value={fromCurrency} onValueChange={(val) => {
              setFromCurrency(val);
              setResult(null);
            }}>
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent className="bg-card border border-border">
                {initialBalances.map((c) => (
                  <SelectItem key={c.code} value={c.code}>
                    {c.code} - {translations[`currency_${c.code.toLowerCase()}`] || c.label}
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
            <Select value={toCurrency} onValueChange={(val) => {
              setToCurrency(val);
              setResult(null);
            }}>
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent className="bg-card border border-border">
                {initialBalances.map((c) => (
                  <SelectItem key={c.code} value={c.code}>
                    {c.code} - {translations[`currency_${c.code.toLowerCase()}`] || c.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <Button
            onClick={handleConvert}
            disabled={isConverting}
            className="mt-6 gradient-accent text-accent-foreground border-0 px-8"
          >
            {isConverting && <RefreshCw className="w-4 h-4 mr-2 animate-spin" />}
            {translations.converter_convert || "Convert"}
          </Button>
        </div>

        {result && (
          <div className="mt-6 p-4 bg-muted/50 rounded-lg text-center animate-fade-in border border-border/50">
            <p className="text-2xl font-bold text-foreground">
              {parseFloat(amount).toLocaleString("cs-CZ")} {fromCurrency} = {formatResult(result.amount, toCurrency)} {toCurrency}
            </p>
            {result.updatedAt && (
              <p className="text-sm text-muted-foreground mt-2">
                {translations.converter_rate_updated || "Rate updated:"} {result.updatedAt}
              </p>
            )}
          </div>
        )}
      </CardContent>
    </Card>
  );
}