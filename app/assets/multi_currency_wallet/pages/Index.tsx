import { useState } from "react";
import { WalletCard } from "@/components/WalletCard";
import { CurrencyConverter } from "@/components/CurrencyConverter";
import { TotalBalance } from "@/components/TotalBalance";
import { Header } from "@/components/Header";
import { PageHeader } from "@/components/PageHeader";

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
    <>
      <PageHeader />
      <div className="container pb-8 space-y-8">
        {/* Wallet Section */}
        <section>
          <div className="mb-2">
            <Header />
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
    </>
  );
};

export default Index;
