import { CurrencyConverter } from "@/components/CurrencyConverter";
import { Header } from "@/components/Header";

const Converter = () => {
  return (
    <div className="max-w-4xl mx-auto">
      <div className="mb-8">
        <Header />
      </div>
      <h1 className="text-2xl font-bold text-foreground mb-6">Currency Converter</h1>
      <CurrencyConverter />
      
      <div className="mt-8 p-6 bg-card rounded-lg border border-border">
        <h2 className="text-lg font-semibold text-foreground mb-4">Quick Tips</h2>
        <ul className="space-y-2 text-muted-foreground">
          <li>• Use the swap button (↔) to quickly reverse the conversion direction</li>
          <li>• Rates are updated periodically - click "Update rates" in the header for fresh data</li>
          <li>• Bitcoin conversions show up to 8 decimal places for precision</li>
          <li>• All major fiat currencies and Bitcoin are supported</li>
        </ul>
      </div>
    </div>
  );
};

export default Converter;
