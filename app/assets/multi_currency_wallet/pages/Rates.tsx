/**
 * Stránka historie směnných kurzů.
 *
 * Zobrazuje tabulku referenčních kurzů a interaktivní graf
 * s historií kurzů a možností zobrazení prognózy.
 *
 * @module Rates
 */

import { RatesHistoryTable } from "@/components/RatesHistoryTable";
import { RatesChart } from "@/components/RatesChart";
import { Header } from "@/components/Header";
import { PageHeader } from "@/components/PageHeader";
import { History } from "lucide-react";
import { useAppConfig } from "@/context/AppConfigContext";

/**
 * Hlavní komponenta stránky kurzů.
 *
 * Obsahuje:
 * - Tabulku referenčních kurzů (RatesHistoryTable)
 * - Interaktivní graf kurzů s prognózou (RatesChart)
 *
 * @returns JSX element stránky
 */
const Rates = () => {
  const { translations } = useAppConfig();

  return (
    <>
      <PageHeader />
      <div className="container pb-8">
        <div className="max-w-5xl mx-auto">
          <div className="mb-2">
            <Header />
          </div>
          <div className="flex items-center gap-3 mb-6">
            <div className="w-10 h-10 rounded-xl bg-signature-rich-indigo flex items-center justify-center">
              <History className="w-5 h-5 text-primary-foreground" />
            </div>
            <h1 className="text-2xl font-bold text-foreground">
              {translations.rates_title || "Exchange Rates History"}
            </h1>
          </div>

          {/* Tabulka referenčních kurzů */}
          <RatesHistoryTable />

          {/* Graf kurzů s možností zobrazení prognózy */}
          <div className="mt-8">
            <RatesChart initialCurrency="EUR" initialDays={30} />
          </div>
        </div>
      </div>
    </>
  );
};

export default Rates;
