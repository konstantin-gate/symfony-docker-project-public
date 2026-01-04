import { useState } from "react";
import { WalletCard } from "@/components/WalletCard";
import { TotalBalance } from "@/components/TotalBalance";
import { Header } from "@/components/Header";
import { PageHeader } from "@/components/PageHeader";
import { useAppConfig } from "@/context/AppConfigContext";
import { toast } from "sonner";
import { Wallet } from "lucide-react";

const Index = () => {
  const { initialBalances, translations } = useAppConfig();

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
        <div className="max-w-5xl mx-auto space-y-6">
          {/* Wallet Section */}
          <section>
            <div className="mb-2">
              <Header />
            </div>

            <div className="flex items-center gap-3 mb-6">
              <div className="w-10 h-10 rounded-xl bg-signature-rich-indigo flex items-center justify-center">
                <Wallet className="w-5 h-5 text-primary-foreground" />
              </div>
              <h1 className="text-2xl font-bold text-foreground">
                {translations.dashboard_title || "Multi-Currency Wallet"}
              </h1>
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
      </div>
    </>
  );
};

export default Index;
