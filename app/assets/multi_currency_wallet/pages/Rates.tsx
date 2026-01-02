import { RatesHistoryTable } from "@/components/RatesHistoryTable";
import { Header } from "@/components/Header";
import { PageHeader } from "@/components/PageHeader";
import { History } from "lucide-react";
import { useAppConfig } from "@/context/AppConfigContext";

const Rates = () => {
  const { translations } = useAppConfig();

  return (
    <>
      <PageHeader />
      <div className="container pb-8">
        <div className="max-w-4xl mx-auto">
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
          <RatesHistoryTable />
        </div>
      </div>
    </>
  );
};

export default Rates;
