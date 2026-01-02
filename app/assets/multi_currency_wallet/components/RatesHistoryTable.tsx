import { TrendingUp } from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { useAppConfig } from "@/context/AppConfigContext";

const ratesHistory = [
  { pair: "USD → EUR", rate: "0.85", updated: "31 Dec 2025 01:00" },
  { pair: "USD → CZK", rate: "22.50", updated: "31 Dec 2025 01:00" },
  { pair: "EUR → CZK", rate: "26.47", updated: "31 Dec 2025 01:00" },
  { pair: "USD → RUB", rate: "89.50", updated: "31 Dec 2025 01:00" },
  { pair: "USD → JPY", rate: "148.50", updated: "31 Dec 2025 01:00" },
  { pair: "BTC → USD", rate: "34,567.89", updated: "31 Dec 2025 01:00" },
  { pair: "BTC → EUR", rate: "29,382.71", updated: "31 Dec 2025 01:00" },
  { pair: "EUR → USD", rate: "1.18", updated: "31 Dec 2025 01:00" },
];

export function RatesHistoryTable() {
  const { translations } = useAppConfig();

  return (
    <Card className="bg-card border border-border">
      <CardHeader>
        <CardTitle className="text-xl font-semibold text-foreground flex items-center gap-2">
          <TrendingUp className="w-5 h-5 text-accent" />
          {translations.rates_title || "Exchange Rates History"}
        </CardTitle>
      </CardHeader>
      <CardContent>
        <div className="rounded-lg border border-border overflow-hidden">
          <Table>
            <TableHeader>
              <TableRow className="bg-muted hover:bg-muted">
                <TableHead className="font-semibold text-foreground">{translations.rates_pair || "Currency Pair"}</TableHead>
                <TableHead className="font-semibold text-foreground">{translations.rates_rate || "Rate"}</TableHead>
                <TableHead className="font-semibold text-foreground">{translations.rates_updated || "Last Updated"}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {ratesHistory.map((row, index) => (
                <TableRow key={index} className="hover:bg-secondary/50">
                  <TableCell className="font-medium text-foreground">{row.pair}</TableCell>
                  <TableCell className="text-foreground">{row.rate}</TableCell>
                  <TableCell className="text-muted-foreground">{row.updated}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      </CardContent>
    </Card>
  );
}