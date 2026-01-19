import { CurrencyConverter } from "@/components/CurrencyConverter";
import { Header } from "@/components/Header";
import { PageHeader } from "@/components/PageHeader";
import { ArrowRightLeft } from "lucide-react";
import { useAppConfig } from "@/context/AppConfigContext";

const Converter = () => {
  const { translations } = useAppConfig();

  return (
    <>
      <PageHeader />
      <div className="container pb-8" data-testid="converter-page">
        <div className="max-w-5xl mx-auto">
          <div className="mb-2">
            <Header />
          </div>
          <div className="flex items-center gap-3 mb-6">
            <div className="w-10 h-10 rounded-xl bg-signature-rich-indigo flex items-center justify-center">
              <ArrowRightLeft className="w-5 h-5 text-primary-foreground" />
            </div>
            <h1 className="text-2xl font-bold text-foreground" data-testid="converter-page-title">
              {translations.converter_title || "Currency Converter"}
            </h1>
          </div>
          <CurrencyConverter />

          <div className="mt-8 p-6 bg-card rounded-lg border border-border" data-testid="converter-tips-section">
            <h2 className="text-lg font-semibold text-foreground mb-4">{translations.quick_tips_title || "Quick Tips"}</h2>
            <ul className="space-y-2 text-muted-foreground">
              <li>{translations.quick_tips_swap_tip || "Use the swap button (↔) to quickly reverse the conversion direction"}</li>
              <li>{translations.quick_tips_update_tip || "Rates are updated periodically - click 'Update rates' in the header for fresh data"}</li>
              <li>{translations.quick_tips_btc_tip || "Bitcoin conversions show up to 8 decimal places for precision"}</li>
              <li>{translations.quick_tips_supported_tip || "All major fiat currencies and Bitcoin are supported"}</li>
            </ul>
          </div>
        </div>
      </div>
    </>
  );
};

export default Converter;
