import { useAppConfig } from "@/context/AppConfigContext";
import { Button } from "@/components/ui/button";
import { Home, ChevronDown } from "lucide-react";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";

export function PageHeader() {
  const { locale, homeUrl, title, backText, iconUrl } = useAppConfig();

  const switchLocale = (newLocale: string) => {
    // Replace the locale segment in the URL.
    // Assumes URL structure like /locale/something...
    const currentPath = window.location.pathname;
    // Simple regex replacement for the first path segment if it matches current locale
    // or just strict string replacement.
    const newPath = currentPath.replace(`/${locale}/`, `/${newLocale}/`);
    window.location.href = newPath;
  };

  const languages = [
    { code: 'cs', label: 'čeština' },
    { code: 'en', label: 'english' },
    { code: 'ru', label: 'русский' },
  ];

  return (
    <div className="flex justify-between items-center mb-6">
      {/* Back to Home */}
      <a href={homeUrl} className="inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 border border-input bg-background hover:bg-accent hover:text-accent-foreground h-9 px-3 py-2">
        <Home className="w-4 h-4 mr-2" />
        {backText}
      </a>

      {/* Center Title */}
      <div className="flex items-center">
        <div className="border rounded p-1 mr-3 flex items-center justify-center w-12 h-12">
            <img src={iconUrl} alt="" className="max-h-full max-w-full h-auto w-auto" />
        </div>
        <h1 className="text-2xl font-bold text-foreground">{title}</h1>
      </div>

      {/* Language Switcher */}
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button variant="outline" size="sm" className="gap-2 uppercase">
            {locale}
            <ChevronDown className="w-4 h-4" />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
            {languages.map((lang) => (
                <DropdownMenuItem key={lang.code} onClick={() => switchLocale(lang.code)}>
                    {lang.label}
                </DropdownMenuItem>
            ))}
        </DropdownMenuContent>
      </DropdownMenu>
    </div>
  );
}
