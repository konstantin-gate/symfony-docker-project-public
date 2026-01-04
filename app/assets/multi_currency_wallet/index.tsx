import { createRoot } from "react-dom/client";
import App from "./App";
import "./index.css";
import { AppConfig, WalletSettings } from "./context/AppConfigContext";

const rootElement = document.getElementById("multi-currency-wallet-root");

if (!rootElement) {
  throw new Error("Failed to find multi-currency-wallet-root element");
}

const currentUrl = new URL(window.location.href);
const pathSegments = currentUrl.pathname.split('/');
// Expecting /.../multi-currency-wallet/...
const walletIndex = pathSegments.indexOf('multi-currency-wallet');
const basename = pathSegments.slice(0, walletIndex + 1).join('/');

const locale = rootElement.getAttribute("data-locale") || "en";

let translations: Record<string, string> = {};
try {
  const translationsAttr = rootElement.getAttribute("data-translations");
  if (translationsAttr) {
    translations = JSON.parse(translationsAttr);
  }
} catch (e) {
  console.error("Failed to parse translations", e);
}

let initialBalances: any[] = [];
try {
  const balancesAttr = rootElement.getAttribute("data-balances");
  if (balancesAttr) {
    initialBalances = JSON.parse(balancesAttr);
  }
} catch (e) {
  console.error("Failed to parse initial balances", e);
}

let initialSettings: WalletSettings = {
  mainCurrency: "CZK",
  autoUpdateEnabled: true,
};
try {
  const configAttr = rootElement.getAttribute("data-config");
  if (configAttr) {
    initialSettings = JSON.parse(configAttr);
  }
} catch (e) {
  console.error("Failed to parse initial configuration", e);
}

const autoUpdateNeeded = rootElement.getAttribute("data-auto-update-needed") === "true";

const config: AppConfig = {
  basename,
  locale,
  homeUrl: `/${locale}`, 
  title: translations['dashboard_title'] || translations['menu_wallet'] || "Multi Currency Wallet",
  backText: translations['dashboard_back_to_home'] || "Back", 
  iconUrl: "/images/icon_multi_currency_wallet.png",
  translations,
  initialBalances,
  autoUpdateNeeded,
  initialSettings,
};

createRoot(rootElement).render(<App config={config} />);