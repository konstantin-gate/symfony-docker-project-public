import { useState } from "react";
import { Pencil, Check, X } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Card, CardContent } from "@/components/ui/card";

interface WalletCardProps {
  currency: string;
  symbol: string;
  balance: number;
  icon: string;
  onBalanceChange: (newBalance: number) => void;
}

export function WalletCard({ currency, symbol, balance, icon, onBalanceChange }: WalletCardProps) {
  const [isEditing, setIsEditing] = useState(false);
  const [editValue, setEditValue] = useState(balance.toString());

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
    if (currency === "BTC") {
      return value.toFixed(8);
    }
    return value.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  };

  return (
    <Card className="card-hover bg-card border border-border">
      <CardContent className="!p-5">
        <div className="flex items-center justify-between mb-4">
          <div className="flex items-center gap-3">
            <span className="text-2xl">{icon}</span>
            <span className="font-semibold text-foreground">{currency}</span>
          </div>
          {!isEditing && (
            <Button
              variant="ghost"
              size="sm"
              onClick={() => setIsEditing(true)}
              className="text-muted-foreground hover:text-foreground"
            >
              <Pencil className="w-4 h-4 mr-1" />
              Edit
            </Button>
          )}
        </div>

        {isEditing ? (
          <div className="space-y-3">
            <Input
              type="number"
              value={editValue}
              onChange={(e) => setEditValue(e.target.value)}
              className="text-lg font-bold"
              step={currency === "BTC" ? "0.00000001" : "0.01"}
            />
            <div className="flex gap-2">
              <Button size="sm" onClick={handleSave} className="flex-1 gradient-accent text-accent-foreground border-0">
                <Check className="w-4 h-4 mr-1" />
                Save
              </Button>
              <Button size="sm" variant="outline" onClick={handleCancel} className="flex-1">
                <X className="w-4 h-4 mr-1" />
                Cancel
              </Button>
            </div>
          </div>
        ) : (
          <div className="text-2xl font-bold text-foreground">
            {symbol}{formatBalance(balance)}
          </div>
        )}
      </CardContent>
    </Card>
  );
}
