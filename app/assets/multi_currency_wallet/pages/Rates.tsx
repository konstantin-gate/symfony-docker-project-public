import { RatesHistoryTable } from "@/components/RatesHistoryTable";

const Rates = () => {
  return (
    <div>
      <h1 className="text-2xl font-bold text-foreground mb-6">Exchange Rates History</h1>
      <RatesHistoryTable />
    </div>
  );
};

export default Rates;
