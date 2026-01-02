import { useState } from "react";
import { Link, useLocation } from "react-router-dom";
import { Wallet, ArrowRightLeft, TrendingUp, Settings, RefreshCw, Menu, X } from "lucide-react";
import { Button } from "@/components/ui/button";
import { toast } from "@/hooks/use-toast";
import { useAppConfig } from "@/context/AppConfigContext";

export function Header() {
  const location = useLocation();
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const [isUpdating, setIsUpdating] = useState(false);
  const { translations } = useAppConfig();

  const navItems = [
    { name: translations['menu_wallet'] || "Wallet", path: "/", icon: Wallet },
    { name: translations['menu_converter'] || "Converter", path: "/converter", icon: ArrowRightLeft },
    { name: translations['menu_rates_history'] || "Rates History", path: "/rates", icon: TrendingUp },
    { name: translations['menu_settings'] || "Settings", path: "/settings", icon: Settings },
  ];

  const handleUpdateRates = () => {
    setIsUpdating(true);
    setTimeout(() => {
      setIsUpdating(false);
      toast({
        title: "Rates Updated",
        description: "Exchange rates have been refreshed successfully.",
      });
    }, 1500);
  };

  return (
    <header className="w-full">
      <div className="flex items-center justify-between h-16">
        {/* Desktop Navigation */}
        <nav className="hidden md:flex items-center gap-1">
          {navItems.map((item) => {
            const Icon = item.icon;
            const isActive = location.pathname === item.path;
            return (
              <Link
                key={item.path}
                to={item.path}
                className={`flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
                  isActive
                    ? "bg-accent text-accent-foreground"
                    : "text-muted-foreground hover:text-foreground hover:bg-secondary"
                }`}
              >
                <Icon className="w-4 h-4" />
                {item.name}
              </Link>
            );
          })}
        </nav>

        <div className="flex items-center gap-2">
          <Button
            onClick={handleUpdateRates}
            disabled={isUpdating}
            className="hidden sm:flex gradient-accent text-accent-foreground border-0 hover:opacity-90"
          >
            <RefreshCw className={`w-4 h-4 mr-2 ${isUpdating ? "animate-spin" : ""}`} />
            {isUpdating ? (translations['menu_updating'] || "Updating...") : (translations['menu_update_rates'] || "Update rates")}
          </Button>

          {/* Mobile Menu Button */}
          <Button
            variant="ghost"
            size="icon"
            className="md:hidden"
            onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
          >
            {mobileMenuOpen ? <X className="w-5 h-5" /> : <Menu className="w-5 h-5" />}
          </Button>
        </div>
      </div>

      {/* Mobile Navigation */}
      {mobileMenuOpen && (
        <nav className="md:hidden bg-card border-t border-border animate-fade-in">
          <div className="container py-4 space-y-1">
            {navItems.map((item) => {
              const Icon = item.icon;
              const isActive = location.pathname === item.path;
              return (
                <Link
                  key={item.path}
                  to={item.path}
                  onClick={() => setMobileMenuOpen(false)}
                  className={`flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition-colors ${
                    isActive
                      ? "bg-accent text-accent-foreground"
                      : "text-muted-foreground hover:text-foreground hover:bg-secondary"
                  }`}
                >
                  <Icon className="w-5 h-5" />
                  {item.name}
                </Link>
              );
            })}
            <Button
              onClick={() => {
                handleUpdateRates();
                setMobileMenuOpen(false);
              }}
              disabled={isUpdating}
              className="w-full mt-4 gradient-accent text-accent-foreground border-0"
            >
              <RefreshCw className={`w-4 h-4 mr-2 ${isUpdating ? "animate-spin" : ""}`} />
              {isUpdating ? (translations['menu_updating'] || "Updating...") : (translations['menu_update_rates'] || "Update exchange rates")}
            </Button>
          </div>
        </nav>
      )}
    </header>
  );
}
