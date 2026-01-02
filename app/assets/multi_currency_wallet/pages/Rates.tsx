import { RatesHistoryTable } from "@/components/RatesHistoryTable";
import { Header } from "@/components/Header";
import { PageHeader } from "@/components/PageHeader";

const Rates = () => {
  return (
    <>
      <PageHeader />
      <div className="container pb-8">
        <div className="max-w-4xl mx-auto">
          <div className="mb-2">
            <Header />
          </div>
          <h1 className="text-2xl font-bold text-foreground mb-6">Exchange Rates History</h1>
          <RatesHistoryTable />
        </div>
      </div>
    </>
  );
};

export default Rates;
