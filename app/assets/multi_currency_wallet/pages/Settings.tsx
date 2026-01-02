import { useState } from "react";
import { Settings as SettingsIcon, Save } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
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

const currencies = [
  { code: "CZK", name: "Czech Koruna" },
  { code: "EUR", name: "Euro" },
  { code: "USD", name: "US Dollar" },
  { code: "RUB", name: "Russian Ruble" },
  { code: "JPY", name: "Japanese Yen" },
  { code: "BTC", name: "Bitcoin" },
];

const languages = [
  { code: "en", name: "English" },
  { code: "cs", name: "Čeština" },
  { code: "de", name: "Deutsch" },
  { code: "ru", name: "Русский" },
];

const Settings = () => {
  const [defaultCurrency, setDefaultCurrency] = useState("CZK");
  const [apiKey, setApiKey] = useState("");
  const [language, setLanguage] = useState("en");
  const [autoUpdate, setAutoUpdate] = useState(true);

  const handleSave = () => {
    toast({
      title: "Settings Saved",
      description: "Your preferences have been updated successfully.",
    });
  };

  return (
    <>
      <PageHeader />
      <div className="container pb-8">
        <div className="max-w-4xl mx-auto">
          <div className="mb-2">
            <Header />
          </div>
          <div className="flex items-center gap-3 mb-6">
            <div className="w-10 h-10 rounded-xl gradient-primary flex items-center justify-center">
              <SettingsIcon className="w-5 h-5 text-primary-foreground" />
            </div>
            <h1 className="text-2xl font-bold text-foreground">Settings</h1>
          </div>

          <Card className="bg-card border border-border">
            <CardHeader>
              <CardTitle className="text-lg">Preferences</CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
              {/* Default Currency */}
              <div className="space-y-2">
                <Label htmlFor="default-currency">Default Wallet Currency</Label>
                <Select value={defaultCurrency} onValueChange={setDefaultCurrency}>
                  <SelectTrigger id="default-currency">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent className="bg-card border border-border">
                    {currencies.map((c) => (
                      <SelectItem key={c.code} value={c.code}>
                        {c.code} - {c.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <p className="text-sm text-muted-foreground">
                  This currency will be used as the default for total balance calculations.
                </p>
              </div>

              {/* API Key */}
              <div className="space-y-2">
                <Label htmlFor="api-key">Exchange Rate API Key</Label>
                <Input
                  id="api-key"
                  type="password"
                  value={apiKey}
                  onChange={(e) => setApiKey(e.target.value)}
                  placeholder="Enter your API key"
                />
                <p className="text-sm text-muted-foreground">
                  Optional. Required for real-time exchange rate updates.
                </p>
              </div>

              {/* Language */}
              <div className="space-y-2">
                <Label htmlFor="language">Language</Label>
                <Select value={language} onValueChange={setLanguage}>
                  <SelectTrigger id="language">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent className="bg-card border border-border">
                    {languages.map((l) => (
                      <SelectItem key={l.code} value={l.code}>
                        {l.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              {/* Auto Update Toggle */}
              <div className="flex items-center justify-between">
                <div className="space-y-0.5">
                  <Label htmlFor="auto-update">Automatic Daily Rate Update</Label>
                  <p className="text-sm text-muted-foreground">
                    Automatically fetch new exchange rates once per day.
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
                className="w-full gradient-accent text-accent-foreground border-0"
              >
                <Save className="w-4 h-4 mr-2" />
                Save Settings
              </Button>
            </CardContent>
          </Card>
        </div>
      </div>
    </>
  );
};

export default Settings;