import { Toaster } from "@/components/ui/toaster";
import { Toaster as Sonner } from "@/components/ui/sonner";
import { TooltipProvider } from "@/components/ui/tooltip";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { BrowserRouter, Routes, Route } from "react-router-dom";
import { MainLayout } from "@/layouts/MainLayout";
import Index from "./pages/Index";
import Converter from "./pages/Converter";
import Rates from "./pages/Rates";
import Settings from "./pages/Settings";
import NotFound from "./pages/NotFound";
import { AppConfig, AppConfigProvider } from "@/context/AppConfigContext";

const queryClient = new QueryClient();

interface AppProps {
  config: AppConfig;
}

const App = ({ config }: AppProps) => (
  <QueryClientProvider client={queryClient}>
    <AppConfigProvider config={config}>
      <TooltipProvider>
        <Toaster />
        <Sonner />
        <BrowserRouter basename={config.basename}>
          <MainLayout>
            <Routes>
              <Route path="/" element={<Index />} />
              <Route path="/converter" element={<Converter />} />
              <Route path="/rates" element={<Rates />} />
              <Route path="/settings" element={<Settings />} />
              <Route path="*" element={<NotFound />} />
            </Routes>
          </MainLayout>
        </BrowserRouter>
      </TooltipProvider>
    </AppConfigProvider>
  </QueryClientProvider>
);

export default App;
