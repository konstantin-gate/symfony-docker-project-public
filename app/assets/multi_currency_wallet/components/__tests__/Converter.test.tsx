import React from 'react';
import '@testing-library/jest-dom';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { CurrencyConverter } from '../CurrencyConverter';
import { useAppConfig } from '@/context/AppConfigContext';
import { TooltipProvider } from '../ui/tooltip';

/**
 * Mockování modulu AppConfigContext pro účely testování.
 * Umožňuje simulovat různé konfigurace a překlady bez závislosti na reálném kontextu aplikace.
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
 * Zajišťuje, aby komponenty jako Tooltip fungovaly správně v testovacím prostředí.
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
 * Testovací suita pro komponentu CurrencyConverter.
 * Prověřuje uživatelské rozhraní převodníku měn, validaci vstupů a komunikaci s backendem.
 */
describe('CurrencyConverter', () => {
    const initialBalances = [
        { code: 'USD', label: 'US Dollar', symbol: '$', decimals: 2 },
        { code: 'EUR', label: 'Euro', symbol: '€', decimals: 2 },
        { code: 'CZK', label: 'Czech Koruna', symbol: 'Kč', decimals: 2 },
    ];

    /**
     * Nastavení výchozího stavu před každým testem.
     * Inicializuje mocky překladů a globální funkci fetch.
     */
    beforeEach(() => {
        mockUseAppConfig.mockReturnValue({
            translations: {
                converter_title: 'Currency Converter',
                converter_convert: 'Convert',
                currency_usd: 'Americký dolar',
                currency_eur: 'Euro',
            },
            initialBalances: initialBalances,
            locale: 'cs-CZ',
        });

        // Mockování globální funkce fetch
        global.fetch = jest.fn();
    });

    /**
     * Úklid po každém testu.
     * Obnovuje všechny mocky do původního stavu.
     */
    afterEach(() => {
        jest.restoreAllMocks();
    });

    /**
     * Testuje aktualizaci částky a spuštění převodu.
     * Ověřuje, že po změně vstupu a kliknutí na tlačítko se odešle korektní API požadavek
     * a výsledek se správně zobrazí uživateli.
     */
    test('updates amount and triggers conversion', async () => {
        (global.fetch as jest.Mock).mockResolvedValue({
            ok: true,
            json: async () => ({ amount: '92.00', rate: '0.92', updatedAt: '2023-01-01T10:00:00Z' }),
        });

        renderWithProviders(<CurrencyConverter />);

        const input = screen.getByPlaceholderText(/enter amount/i);
        fireEvent.change(input, { target: { value: '200' } });

        const convertButton = screen.getByRole('button', { name: /convert/i });
        fireEvent.click(convertButton);

        await waitFor(() => {
            // Použití regulárního výrazu pro ošetření rozdílů ve formátování mezer (např. nezalomitelná mezera)
            expect(screen.getByText(/200\s*USD\s*=\s*92,00\s*EUR/i)).toBeInTheDocument();
        });

        expect(global.fetch).toHaveBeenCalledWith('/api/multi-currency-wallet/convert', expect.objectContaining({
            method: 'POST',
            body: JSON.stringify({ amount: '200', from: 'USD', to: 'EUR' }),
        }));
    });

    /**
     * Testuje funkci prohození (swap) zdrojové a cílové měny.
     * Ověřuje, že tlačítko swap správně mění směr převodu v datech odesílaných na server.
     */
    test('swaps currencies', () => {
        renderWithProviders(<CurrencyConverter />);

        // Výchozí stav komponenty je převod z USD do EUR
        // Vyhledání tlačítka pro prohození měn (ikona ArrowRightLeft)
        const buttons = screen.getAllByRole('button');
        const swapButton = buttons[0]; // První tlačítko v pořadí JSX je swap

        fireEvent.click(swapButton);

        // Nyní by měl převod probíhat z EUR do USD
        (global.fetch as jest.Mock).mockResolvedValue({
            ok: true,
            json: async () => ({ amount: '108.70', rate: '1.087', updatedAt: '2023-01-01T10:00:00Z' }),
        });

        fireEvent.click(screen.getByRole('button', { name: /convert/i }));

        expect(global.fetch).toHaveBeenCalledWith('/api/multi-currency-wallet/convert', expect.objectContaining({
            body: expect.stringContaining('"from":"EUR","to":"USD"'),
        }));
    });
});