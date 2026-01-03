import { useState } from "react";
import { Pencil, Check, X } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Card, CardContent } from "@/components/ui/card";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";
import { useAppConfig } from "@/context/AppConfigContext";

interface WalletCardProps {
  currency: string;
  symbol: string;
  balance: number;
  icon: string;
  decimals: number;
  onBalanceChange: (newBalance: number) => void;
}

export function WalletCard({ currency, symbol, balance, icon, decimals, onBalanceChange }: WalletCardProps) {
  const [isEditing, setIsEditing] = useState(false);
  const [editValue, setEditValue] = useState(balance.toString());
  const { translations } = useAppConfig();

  const handleSave = () => {
    const newBalance = parseFloat(editValue) || 0;
    onBalanceChange(newBalance);
    setIsEditing(false);
  };

  const handleCancel = () => {
    setEditValue(balance.toString());
    setIsEditing(false);
  };

  const formatBalance = (value: number) => {
    return value.toLocaleString("cs-CZ", { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
  };

  // Calculate step based on decimals (e.g., 2 decimals -> 0.01, 0 decimals -> 1, 8 decimals -> 0.00000001)
  const step = decimals === 0 ? "1" : `0.${"0".repeat(decimals - 1)}1`;

  return (
    <Card className="card-hover bg-card border border-border">
      <CardContent className="!p-5">
        <div className="flex items-center justify-between mb-4">
          <div className="flex items-center gap-3">
            <div className="inline-flex items-center justify-center h-10 w-10 rounded-md border border-input bg-background text-sm font-medium shadow-sm">
              {symbol}
            </div>
            <span className="font-light text-foreground">
              {currency} - {translations[`currency_${currency.toLowerCase()}`] || ""}
            </span>
          </div>
          {!isEditing && (
            <Tooltip>
              <TooltipTrigger asChild>
                <Button
                  variant="ghost"
                  size="icon"
                  onClick={() => setIsEditing(true)}
                  className="text-muted-foreground hover:text-foreground h-8 w-8"
                >
                  <Pencil className="w-4 h-4" />
                </Button>
              </TooltipTrigger>
              <TooltipContent>
                <p>{translations['card_edit'] || "Edit"}</p>
              </TooltipContent>
            </Tooltip>
          )}
        </div>

        {isEditing ? (
          <div className="space-y-3">
            <Input
              type="number"
              value={editValue}
              onChange={(e) => setEditValue(e.target.value)}
              className="text-lg font-bold"
              step={step}
            />
            <div className="flex gap-2">
              <Button size="sm" onClick={handleSave} className="flex-1 gradient-accent text-accent-foreground border-0">
                <Check className="w-4 h-4 mr-1" />
                {translations['card_save'] || "Save"}
              </Button>
              <Button size="sm" variant="outline" onClick={handleCancel} className="flex-1">
                <X className="w-4 h-4 mr-1" />
                {translations['card_cancel'] || "Cancel"}
              </Button>
            </div>
          </div>
        ) : (
          <div className="text-2xl font-bold text-foreground flex items-center gap-3">
            <span>{symbol}</span>
            <span>{formatBalance(balance)}</span>
          </div>
        )}
      </CardContent>
    </Card>
  );
}
