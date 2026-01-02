import { useState } from "react";
import { Link, useLocation } from "react-router-dom";
import { Wallet, ArrowRightLeft, TrendingUp, Settings, RefreshCw, Menu, X } from "lucide-react";
import { Button } from "@/components/ui/button";
import { toast } from "@/hooks/use-toast";

const navItems = [
  { name: "Wallet", path: "/", icon: Wallet },
  { name: "Converter", path: "/converter", icon: ArrowRightLeft },
  { name: "Rates History", path: "/rates", icon: TrendingUp },
  { name: "Settings", path: "/settings", icon: Settings },
];

export function Header() {
  const location = useLocation();
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const [isUpdating, setIsUpdating] = useState(false);

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
    <header className="fixed top-0 left-0 right-0 z-50 bg-card border-b border-border shadow-sm">
      <div className="container flex items-center justify-between h-16">
        <Link to="/" className="flex items-center gap-2">
          <div className="w-8 h-8 rounded-lg gradient-accent flex items-center justify-center">
            <Wallet className="w-5 h-5 text-accent-foreground" />
          </div>
          <span className="font-semibold text-lg text-foreground hidden sm:inline">
            Multi-Currency Pocket
          </span>
        </Link>

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
            {isUpdating ? "Updating..." : "Update rates"}
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
              {isUpdating ? "Updating..." : "Update exchange rates"}
            </Button>
          </div>
        </nav>
      )}
    </header>
  );
}
