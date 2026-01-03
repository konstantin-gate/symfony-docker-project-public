import React, { createContext, useContext, ReactNode } from 'react';

export interface AppConfig {
  basename: string;
  locale: string;
  homeUrl: string;
  title: string;
  backText: string;
  iconUrl: string;
  translations: Record<string, string>;
  initialBalances: Array<{
    code: string;
    amount: number;
    symbol: string;
    icon: string;
    label: string;
    decimals: number;
  }>;
  autoUpdateNeeded: boolean;
}

const AppConfigContext = createContext<AppConfig | undefined>(undefined);

export const useAppConfig = () => {
  const context = useContext(AppConfigContext);
  if (!context) {
    throw new Error('useAppConfig must be used within an AppConfigProvider');
  }
  return context;
};

interface AppConfigProviderProps {
  config: AppConfig;
  children: ReactNode;
}

export const AppConfigProvider = ({ config, children }: AppConfigProviderProps) => (
  <AppConfigContext.Provider value={config}>
    {children}
  </AppConfigContext.Provider>
);
