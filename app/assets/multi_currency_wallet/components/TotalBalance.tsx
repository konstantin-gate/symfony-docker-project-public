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
          Total Wallet Value
        </CardTitle>
      </CardHeader>
      <CardContent>
        <div className="flex flex-col sm:flex-row items-center gap-4">
          <div className="flex-1 w-full sm:max-w-xs">
            <label className="text-sm font-medium text-muted-foreground mb-2 block">
              Target Currency
            </label>
            <Select value={targetCurrency} onValueChange={setTargetCurrency}>
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent className="bg-card border border-border">
                {currencies.map((c) => (
                  <SelectItem key={c.code} value={c.code}>
                    {c.symbol} {c.code} - {c.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <Button
            onClick={handleCalculate}
            className="sm:mt-6 gradient-accent text-accent-foreground border-0 px-8"
          >
            Calculate Total
          </Button>
        </div>

        {total !== null && (
          <div className="mt-6 p-6 gradient-primary rounded-lg text-center animate-fade-in">
            <p className="text-sm text-primary-foreground/80 mb-2">Total Balance</p>
            <p className="text-3xl font-bold text-primary-foreground">
              {getSymbol()}{formatTotal(total)} {targetCurrency}
            </p>
          </div>
        )}
      </CardContent>
    </Card>
  );
}
