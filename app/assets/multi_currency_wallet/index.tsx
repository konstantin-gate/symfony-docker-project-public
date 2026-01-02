import { createRoot } from "react-dom/client";
import App from "./App";
import "./index.css";

const rootElement = document.getElementById("root");
const basename = rootElement?.getAttribute("data-basename") || "/";

createRoot(rootElement!).render(<App basename={basename} />);
