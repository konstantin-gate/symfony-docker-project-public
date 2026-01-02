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

const config = {
  basename,
  locale,
  homeUrl,
  title,
  backText,
  iconUrl,
};

createRoot(rootElement!).render(<App config={config} />);
