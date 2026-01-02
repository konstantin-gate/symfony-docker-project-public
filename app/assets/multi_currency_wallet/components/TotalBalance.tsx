import { useState } from "react";
import { Calculator } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { useAppConfig } from "@/context/AppConfigContext";

interface BalanceData {
  [key: string]: number;
}

interface TotalBalanceProps {
  balances: BalanceData;
}

const currencies = [
  { code: "CZK", symbol: "Kč", name: "Czech Koruna" },
  { code: "EUR", symbol: "€", name: "Euro" },
  { code: "USD", symbol: "$", name: "US Dollar" },
  { code: "RUB", symbol: "₽", name: "Russian Ruble" },
  { code: "JPY", symbol: "¥", name: "Japanese Yen" },
  { code: "BTC", symbol: "₿", name: "Bitcoin" },
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

export function TotalBalance({ balances }: TotalBalanceProps) {
  const [targetCurrency, setTargetCurrency] = useState("CZK");
  const [total, setTotal] = useState<number | null>(null);
  const { translations } = useAppConfig();

  const handleCalculate = () => {
    let totalInUsd = 0;
    
    Object.entries(balances).forEach(([currency, balance]) => {
      const rate = rates[currency] || 1;
      totalInUsd += balance / rate;
    });

    const targetRate = rates[targetCurrency] || 1;
    const totalInTarget = totalInUsd * targetRate;
    setTotal(totalInTarget);
  };

  const formatTotal = (value: number) => {
    if (targetCurrency === "BTC") {
      return value.toFixed(8);
    }
    return value.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  };

  const getSymbol = () => {
    return currencies.find(c => c.code === targetCurrency)?.symbol || "";
  };

  return (
    <Card className="bg-card border border-border">
      <CardHeader>
        <CardTitle className="text-xl font-semibold text-foreground flex items-center gap-2">
          <Calculator className="w-5 h-5 text-accent" />
          {translations['total_balance_title'] || "Total Wallet Value"}
        </CardTitle>
      </CardHeader>
      <CardContent>
        <div className="flex flex-col md:flex-row items-end gap-4">
          <div className="flex-1 w-full md:max-w-xs">
            <label className="text-sm font-medium text-muted-foreground mb-2 block">
              {translations['total_balance_target_currency'] || "Target Currency"}
            </label>
            <Select value={targetCurrency} onValueChange={setTargetCurrency}>
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent className="bg-card border border-border">
                {currencies.map((c) => (
                  <SelectItem key={c.code} value={c.code}>
                    {c.symbol} {c.code} - {translations[`currency_${c.code.toLowerCase()}`] || c.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <Button
            onClick={handleCalculate}
            className="w-full md:w-auto gradient-accent text-accent-foreground border-0 px-8 h-10"
          >
            {translations['total_balance_calculate'] || "Calculate Total"}
          </Button>

          {total !== null && (
            <div className="flex-1 w-full md:w-auto h-10 gradient-primary rounded-md flex items-center justify-center px-6 animate-fade-in">
              <p className="text-lg font-bold text-primary-foreground whitespace-nowrap">
                <span className="text-sm font-normal text-primary-foreground/80 mr-2">
                  {translations['total_balance_result_label'] || "Total Balance"}:
                </span>
                <span className="mr-2">{getSymbol()}</span>
                {formatTotal(total)} {targetCurrency}
              </p>
            </div>
          )}
        </div>
      </CardContent>
    </Card>
  );
}
