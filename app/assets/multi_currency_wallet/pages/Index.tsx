import { useState } from "react";
import { WalletCard } from "@/components/WalletCard";
import { TotalBalance } from "@/components/TotalBalance";
import { Header } from "@/components/Header";
import { PageHeader } from "@/components/PageHeader";
import { useAppConfig } from "@/context/AppConfigContext";

const Index = () => {
  const { initialBalances } = useAppConfig();

  // Convert array from backend to map for easier state updates
  const [balances, setBalances] = useState<Record<string, number>>(() => 
    initialBalances.reduce((acc, item) => {
      acc[item.code] = item.amount;
      return acc;
    }, {} as Record<string, number>)
  );

  const handleBalanceChange = (currency: string, newBalance: number) => {
    setBalances((prev) => ({
      ...prev,
      [currency]: newBalance,
    }));
  };

  return (
    <>
      <PageHeader />
      <div className="container pb-8 !space-y-6">
        {/* Wallet Section */}
        <section>
          <div className="mb-2">
            <Header />
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 !gap-4">
            {initialBalances.map((item) => {
              const code = item.code;
              
              return (
                <WalletCard
                  key={code}
                  currency={code}
                  symbol={item.symbol}
                  balance={balances[code]}
                  icon={item.icon}
                  decimals={item.decimals}
                  onBalanceChange={(newBalance) => handleBalanceChange(code, newBalance)}
                />
              );
            })}
          </div>
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
