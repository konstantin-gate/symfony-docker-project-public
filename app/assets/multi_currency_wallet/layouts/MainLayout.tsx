import { ReactNode } from "react";

interface MainLayoutProps {
  children: ReactNode;
}

export function MainLayout({ children }: MainLayoutProps) {
  return (
    <div className="fixed inset-0 w-full flex flex-col bg-background overflow-hidden">
      <main className="flex-1 overflow-y-auto w-full">
        {children}
      </main>
    </div>
  );
}
