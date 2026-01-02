import { useState } from "react";
import { Wallet } from "lucide-react";
import { WalletCard } from "@/components/WalletCard";
import { CurrencyConverter } from "@/components/CurrencyConverter";
import { TotalBalance } from "@/components/TotalBalance";

const initialBalances = {
  CZK: 125000,
  EUR: 2500,
  USD: 3200,
  RUB: 45000,
  JPY: 150000,
  BTC: 0.05,
};

const currencyData = [
  { code: "CZK", symbol: "Kč", icon: "🇨🇿" },
  { code: "EUR", symbol: "€", icon: "🇪🇺" },
  { code: "USD", symbol: "$", icon: "🇺🇸" },
  { code: "RUB", symbol: "₽", icon: "🇷🇺" },
  { code: "JPY", symbol: "¥", icon: "🇯🇵" },
  { code: "BTC", symbol: "₿", icon: "₿" },
];

const Index = () => {
  const [balances, setBalances] = useState(initialBalances);

  const handleBalanceChange = (currency: string, newBalance: number) => {
    setBalances((prev) => ({
      ...prev,
      [currency]: newBalance,
    }));
  };

  return (
    <div className="space-y-8">
      {/* Wallet Section */}
      <section>
        <div className="flex items-center gap-3 mb-6">
          <div className="w-10 h-10 rounded-xl gradient-primary flex items-center justify-center">
            <Wallet className="w-5 h-5 text-primary-foreground" />
          </div>
          <h1 className="text-2xl font-bold text-foreground">My Wallet</h1>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {currencyData.map((currency) => (
            <WalletCard
              key={currency.code}
              currency={currency.code}
              symbol={currency.symbol}
              balance={balances[currency.code as keyof typeof balances]}
              icon={currency.icon}
              onBalanceChange={(newBalance) => handleBalanceChange(currency.code, newBalance)}
            />
          ))}
        </div>
      </section>

      {/* Currency Converter */}
      <section>
        <CurrencyConverter />
      </section>

      {/* Total Balance */}
      <section>
        <TotalBalance balances={balances} />
      </section>
    </div>
  );
};

export default Index;
