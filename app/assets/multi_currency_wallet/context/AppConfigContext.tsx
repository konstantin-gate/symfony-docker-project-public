import React, { createContext, useContext, ReactNode, useState } from 'react';

export interface WalletSettings {
  mainCurrency: string;
  autoUpdateEnabled: boolean;
}

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
  initialSettings: WalletSettings;
}

interface AppConfigContextValue extends AppConfig {
  walletSettings: WalletSettings;
  setWalletSettings: (settings: WalletSettings) => void;
}

const AppConfigContext = createContext<AppConfigContextValue | undefined>(undefined);

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

export const AppConfigProvider = ({ config, children }: AppConfigProviderProps) => {
  const [walletSettings, setWalletSettings] = useState<WalletSettings>(config.initialSettings);

  return (
    <AppConfigContext.Provider value={{ ...config, walletSettings, setWalletSettings }}>
      {children}
    </AppConfigContext.Provider>
  );
};