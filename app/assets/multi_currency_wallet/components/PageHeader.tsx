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
    window.location.href = currentPath.replace(`/${locale}/`, `/${newLocale}/`);
  };

  const languages = [
    { code: 'cs', label: 'čeština' },
    { code: 'en', label: 'english' },
    { code: 'ru', label: 'русский' },
  ];

  return (
    <header className="bg-white border-b border-border shadow-sm mb-2">
      <div className="container py-4">
        <div className="max-w-5xl mx-auto flex justify-between items-center">
          {/* Back to Home */}
          <a href={homeUrl} className="btn btn-outline-secondary btn-sm d-inline-flex align-items-center" style={{ fontFamily: 'var(--bs-body-font-family)' }}>
            <i className="bi bi-house me-2"></i>
            {backText}
          </a>

          {/* Center Title */}
          <div className="flex items-center">
            <div className="border rounded p-2 mr-4 flex items-center justify-center w-12 h-12">
                <img src={iconUrl} alt="" className="max-h-full max-w-full h-auto w-auto" />
            </div>
            <h1 className="text-[calc(1.375rem_+_1.5vw)] xl:text-[2.5rem] font-medium leading-[1.2] text-foreground" style={{ fontFamily: 'var(--bs-body-font-family)' }}>{title}</h1>
          </div>

          {/* Language Switcher */}
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <button type="button" className="btn btn-outline-secondary btn-sm dropdown-toggle text-uppercase" style={{ fontFamily: 'var(--bs-body-font-family)' }}>
                <span className="me-1">{locale}</span>
              </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent 
                align="end" 
                className="shadow-sm min-w-0" 
                style={{ 
                    backgroundColor: '#fff', 
                    borderColor: '#6c757d', 
                    borderWidth: '1px', 
                    borderStyle: 'solid',
                    borderRadius: '0.25rem', 
                    padding: '0.25rem 0',
                    fontFamily: 'var(--bs-body-font-family)'
                }}
            >
                {languages.map((lang) => (
                    <DropdownMenuItem 
                        key={lang.code} 
                        onClick={() => switchLocale(lang.code)} 
                        className="text-[#212529] text-[0.875rem] font-normal px-3 py-1 focus:bg-[#f8f9fa] focus:text-[#1e2125] cursor-pointer rounded-none" 
                        style={{ fontFamily: 'var(--bs-body-font-family)' }}
                    >
                        {lang.label}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>
    </header>
  );
}
