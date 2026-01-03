import { useState } from "react";
import { WalletCard } from "@/components/WalletCard";
import { TotalBalance } from "@/components/TotalBalance";
import { Header } from "@/components/Header";
import { PageHeader } from "@/components/PageHeader";
import { useAppConfig } from "@/context/AppConfigContext";
import { toast } from "sonner";

const Index = () => {
  const { initialBalances } = useAppConfig();

  // Convert array from backend to map for easier state updates
  const [balances, setBalances] = useState<Record<string, number>>(() => 
    initialBalances.reduce((acc, item) => {
      acc[item.code] = item.amount;
      return acc;
    }, {} as Record<string, number>)
  );

  const handleBalanceChange = async (currency: string, newBalance: number) => {
    const oldBalance = balances[currency];
    
    // Optimistic update
    setBalances((prev) => ({
      ...prev,
      [currency]: newBalance,
    }));

    try {
      const response = await fetch('/api/multi-currency-wallet/update-balance', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          currency,
          amount: newBalance.toString(),
        }),
      });

      if (!response.ok) {
        throw new Error('Failed to update balance');
      }

      toast.success(`${currency} balance updated`);
    } catch (error) {
      // Revert on error
      setBalances((prev) => ({
        ...prev,
        [currency]: oldBalance,
      }));
      toast.error(`Failed to update ${currency} balance`);
      console.error(error);
    }
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
