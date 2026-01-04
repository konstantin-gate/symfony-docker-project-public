import React from 'react';
import '@testing-library/jest-dom';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { TotalBalance } from '../TotalBalance';
import { useAppConfig } from '@/context/AppConfigContext';
import { TooltipProvider } from '../ui/tooltip';

/**
 * Mockování modulu AppConfigContext.
 * Umožňuje testovat komponentu nezávisle na reálném poskytovateli konfigurace.
 */
jest.mock('@/context/AppConfigContext', () => {
    const originalModule = jest.requireActual('@/context/AppConfigContext');
    return {
        ...originalModule,
        useAppConfig: jest.fn(),
    };
});

const mockUseAppConfig = useAppConfig as jest.Mock;

/**
 * Pomocná funkce pro vykreslení komponenty s potřebnými providery.
 * 
 * @param ui React element k vykreslení
 */
const renderWithProviders = (ui: React.ReactElement) => {
    return render(
        <TooltipProvider>
            {ui}
        </TooltipProvider>
    );
};

/**
 * Testovací suita pro komponentu TotalBalance.
 * Prověřuje výpočet celkového zůstatku peněženky a dynamickou změnu cílové měny.
 */
describe('TotalBalance', () => {
    const initialBalances = [
        { code: 'USD', label: 'US Dollar', symbol: '$', decimals: 2 },
        { code: 'EUR', label: 'Euro', symbol: '€', decimals: 2 },
        { code: 'CZK', label: 'Czech Koruna', symbol: 'Kč', decimals: 2 },
    ];

    const balances = {
        'USD': 100,
        'EUR': 50
    };

    /**
     * Příprava mocků před každým testem.
     */
    beforeEach(() => {
        mockUseAppConfig.mockReturnValue({
            translations: {
                total_balance_title: 'Total Wallet Value',
                total_balance_calculate: 'Calculate Total',
                total_balance_result_label: 'Total Balance',
            },
            initialBalances: initialBalances,
        });

        global.fetch = jest.fn();
    });

    /**
     * Úklid po každém testu.
     */
    afterEach(() => {
        jest.restoreAllMocks();
    });

    /**
     * Testuje výpočet a zobrazení celkového zůstatku.
     * Ověřuje, že po kliknutí na tlačítko se provede správný API požadavek a zobrazí se formátovaný výsledek.
     */
    test('calculates and displays total balance', async () => {
        (global.fetch as jest.Mock).mockResolvedValue({
            ok: true,
            json: async () => ({ total: '3400.50', currency: 'CZK' }),
        });

        renderWithProviders(<TotalBalance balances={balances} />);

        const calculateButton = screen.getByRole('button', { name: /calculate total/i });
        fireEvent.click(calculateButton);

        await waitFor(() => {
            expect(screen.getByText(/Total Balance:/i)).toBeInTheDocument();
            // Ověření symbolu měny (může se vyskytovat vícekrát, např. v selectu)
            const symbols = screen.getAllByText(/Kč/i);
            expect(symbols.length).toBeGreaterThan(0);
            
            // Regulární výraz pro ošetření formátování mezer z toLocaleString
            expect(screen.getByText(/3\s*400,50\s*CZK/)).toBeInTheDocument();
        });

        expect(global.fetch).toHaveBeenCalledWith('/api/multi-currency-wallet/calculate-total', expect.objectContaining({
            method: 'POST',
            body: JSON.stringify({ targetCurrency: 'CZK' }),
        }));
    });

    /**
     * Testuje automatický přepočet při změně cílové měny.
     * Ověřuje, že výběr jiné měny v select boxu okamžitě spustí nový výpočet.
     */
    test('automatically recalculates when currency changes', async () => {
        (global.fetch as jest.Mock).mockResolvedValue({
            ok: true,
            json: async () => ({ total: '150.00', currency: 'USD' }),
        });

        renderWithProviders(<TotalBalance balances={balances} />);

        // Otevření Select triggeru
        const selectTrigger = screen.getByRole('combobox');
        fireEvent.click(selectTrigger);

        // Výběr položky (USD) - SelectItems jsou v JSDOM vykresleny v body
        const usdOption = await screen.findByText(/\$ USD/i);
        fireEvent.click(usdOption);

        await waitFor(() => {
            expect(global.fetch).toHaveBeenCalledWith('/api/multi-currency-wallet/calculate-total', expect.objectContaining({
                body: JSON.stringify({ targetCurrency: 'USD' }),
            }));
        });
    });
});