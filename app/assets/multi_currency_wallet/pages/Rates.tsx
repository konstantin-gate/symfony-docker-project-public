import { RatesHistoryTable } from "@/components/RatesHistoryTable";
import { Header } from "@/components/Header";

const Rates = () => {
  return (
    <div>
      <div className="mb-8">
        <Header />
      </div>
      <h1 className="text-2xl font-bold text-foreground mb-6">Exchange Rates History</h1>
      <RatesHistoryTable />
    </div>
  );
};

export default Rates;
