import { useState, useEffect } from "react";
import { Settings as SettingsIcon, Save } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Switch } from "@/components/ui/switch";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { toast } from "@/hooks/use-toast";
import { Header } from "@/components/Header";
import { PageHeader } from "@/components/PageHeader";
import { useAppConfig } from "@/context/AppConfigContext";

const currencies = [
  { code: "CZK", name: "Czech Koruna" },
  { code: "EUR", name: "Euro" },
  { code: "USD", name: "US Dollar" },
  { code: "RUB", name: "Russian Ruble" },
  { code: "JPY", name: "Japanese Yen" },
  { code: "BTC", name: "Bitcoin" },
];

const Settings = () => {
  const { translations, walletSettings, setWalletSettings } = useAppConfig();
  const [mainCurrency, setMainCurrency] = useState(walletSettings.mainCurrency);
  const [autoUpdate, setAutoUpdate] = useState(walletSettings.autoUpdateEnabled);
  const [isSaving, setIsSaving] = useState(false);

  // Sync local state if context changes (e.g. on initial load)
  useEffect(() => {
    setMainCurrency(walletSettings.mainCurrency);
    setAutoUpdate(walletSettings.autoUpdateEnabled);
  }, [walletSettings]);

  const handleSave = async () => {
    setIsSaving(true);
    try {
      const response = await fetch("/api/multi-currency-wallet/settings", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          mainCurrency: mainCurrency,
          autoUpdateEnabled: autoUpdate,
        }),
      });

      if (!response.ok) {
        throw new Error("Failed to save settings");
      }

      const data = await response.json();
      
      // Update global context
      setWalletSettings({
        mainCurrency: data.mainCurrency,
        autoUpdateEnabled: data.autoUpdateEnabled,
      });

      toast({
        title: translations.settings_saved_title || "Settings Saved",
        description: translations.settings_saved_desc || "Your preferences have been updated successfully.",
      });
    } catch (error) {
      toast({
        title: "Error",
        description: "Could not save settings to the server.",
        variant: "destructive",
      });
    } finally {
      setIsSaving(false);
    }
  };

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
              <SettingsIcon className="w-5 h-5 text-primary-foreground" />
            </div>
            <h1 className="text-2xl font-bold text-foreground">
              {translations.settings_title || "Settings"}
            </h1>
          </div>

          <Card className="bg-card border border-border">
            <CardHeader>
              <CardTitle className="text-lg">{translations.settings_preferences || "Preferences"}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
              {/* Main Currency (formerly Default) */}
              <div className="space-y-2">
                <Label htmlFor="main-currency">{translations.settings_default_currency || "Main Wallet Currency"}</Label>
                <Select value={mainCurrency} onValueChange={setMainCurrency}>
                  <SelectTrigger id="main-currency">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent className="bg-card border border-border">
                    {currencies.map((c) => (
                      <SelectItem key={c.code} value={c.code}>
                        {c.code} - {translations[`currency_${c.code.toLowerCase()}`] || c.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <p className="text-sm text-muted-foreground">
                  {translations.settings_default_currency_desc || "This currency will be used as the default for total balance calculations."}
                </p>
              </div>

              {/* Auto Update Toggle */}
              <div className="flex items-center justify-between">
                <div className="space-y-0.5">
                  <Label htmlFor="auto-update">{translations.settings_auto_update || "Automatic Daily Rate Update"}</Label>
                  <p className="text-sm text-muted-foreground">
                    {translations.settings_auto_update_desc || "Automatically fetch new exchange rates once per day."}
                  </p>
                </div>
                <Switch
                  id="auto-update"
                  checked={autoUpdate}
                  onCheckedChange={setAutoUpdate}
                />
              </div>

              {/* Save Button */}
              <Button
                onClick={handleSave}
                disabled={isSaving}
                className="w-full gradient-accent text-accent-foreground border-0"
              >
                <Save className="w-4 h-4 mr-2" />
                {isSaving ? "Saving..." : (translations.settings_save || "Save Settings")}
              </Button>
            </CardContent>
          </Card>
        </div>
      </div>
    </>
  );
};

export default Settings;