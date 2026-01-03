import { createRoot } from "react-dom/client";
import App from "./App";
import "./index.css";

const rootElement = document.getElementById("root");
const basename = rootElement?.getAttribute("data-basename") || "/";
const locale = rootElement?.getAttribute("data-locale") || "en";
const homeUrl = rootElement?.getAttribute("data-home-url") || "/";
const title = rootElement?.getAttribute("data-title") || "Multi Currency Wallet";
const backText = rootElement?.getAttribute("data-back-text") || "Back to home";
const iconUrl = rootElement?.getAttribute("data-icon-url") || "";

let translations = {};
try {
  const translationsAttr = rootElement?.getAttribute("data-translations");
  if (translationsAttr) {
    translations = JSON.parse(translationsAttr);
  }
} catch (e) {
  console.error("Failed to parse translations", e);
}

let initialBalances: Array<{
  code: string;
  amount: number;
  symbol: string;
  icon: string;
  label: string;
  decimals: number;
}> = [];
try {
  const balancesAttr = rootElement?.getAttribute("data-initial-balances");
  if (balancesAttr) {
    initialBalances = JSON.parse(balancesAttr);
  }
} catch (e) {
  console.error("Failed to parse initial balances", e);
}

const config = {
  basename,
  locale,
  homeUrl,
  title,
  backText,
  iconUrl,
  translations,
  initialBalances,
};

createRoot(rootElement!).render(<App config={config} />);
