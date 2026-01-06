import { useState } from "react";
import { Calculator, Loader2 } from "lucide-react";
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
import { toast } from "sonner";

interface BalanceData {
  [key: string]: number;
}

interface TotalBalanceProps {
  balances: BalanceData;
}

export function TotalBalance({ balances }: TotalBalanceProps) {
  const [targetCurrency, setTargetCurrency] = useState("CZK");
  const [total, setTotal] = useState<number | null>(null);
  const [isLoading, setIsLoading] = useState(false);
  const { translations, initialBalances } = useAppConfig();

  const handleCalculate = async (currency: string = targetCurrency) => {
    setIsLoading(true);
    try {
      const response = await fetch('/api/multi-currency-wallet/calculate-total', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ targetCurrency: currency }),
      });

      if (!response.ok) {
        throw new Error('Failed to calculate total');
      }

      const data = await response.json();
      setTotal(parseFloat(data.total));
    } catch (error) {
      toast.error("Calculation failed. Please try again.");
      console.error(error);
    } finally {
      setIsLoading(false);
    }
  };

  const handleCurrencyChange = (newCurrency: string) => {
    setTargetCurrency(newCurrency);
    // Automatically recalculate when currency changes
    handleCalculate(newCurrency);
  };

  const formatTotal = (value: number) => {
    const targetMeta = initialBalances.find(b => b.code === targetCurrency);
    const decimals = targetMeta ? targetMeta.decimals : 2;
    return value.toLocaleString("cs-CZ", { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
  };

  const getSymbol = () => {
    return initialBalances.find(b => b.code === targetCurrency)?.symbol || "";
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
            <Select value={targetCurrency} onValueChange={handleCurrencyChange}>
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent className="bg-card border border-border">
                {initialBalances.map((item) => (
                  <SelectItem key={item.code} value={item.code}>
                    {item.symbol} {item.code} - {item.label || item.code}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <Button
            onClick={() => handleCalculate()}
            disabled={isLoading}
            className="w-full md:w-auto gradient-accent text-accent-foreground border-0 px-8 h-10"
          >
            {isLoading ? <Loader2 className="w-4 h-4 animate-spin mr-2" /> : null}
            {translations['total_balance_calculate'] || "Calculate Total"}
          </Button>

          {total !== null && (
            <div className="flex-1 w-full md:w-auto h-10 rounded-md flex items-center justify-center px-6 animate-fade-in bg-total-balance">
              <p className="text-lg font-bold text-white whitespace-nowrap m-0 flex items-center leading-none">
                <span className="text-sm font-normal text-white/80 mr-2">
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
