import React from 'react';
import '@testing-library/jest-dom';
import { render, screen, fireEvent } from '@testing-library/react';
import { WalletCard } from '../WalletCard';
import { useAppConfig } from '@/context/AppConfigContext';
import { TooltipProvider } from '../ui/tooltip';

/**
 * Mockování modulu AppConfigContext.
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
 * Pomocná funkce pro vykreslení komponenty s TooltipProviderem.
 * 
 * @param ui React element
 */
const renderWithProviders = (ui: React.ReactElement) => {
    return render(
        <TooltipProvider>
            {ui}
        </TooltipProvider>
    );
};

/**
 * Testovací suita pro komponentu WalletCard.
 * Prověřuje zobrazení měny, přepínání režimu editace a ukládání změn zůstatku.
 */
describe('WalletCard', () => {
    const defaultProps = {
        currency: 'USD',
        symbol: '$',
        balance: 100.50,
        icon: 'icon_url',
        decimals: 2,
        onBalanceChange: jest.fn(),
    };

    /**
     * Příprava mocků před každým testem.
     * Zajišťuje čistý stav pro sledování volání onBalanceChange.
     */
    beforeEach(() => {
        defaultProps.onBalanceChange.mockClear();
        mockUseAppConfig.mockReturnValue({
            translations: {
                currency_usd: 'Americký dolar',
                card_edit: 'Upravit',
                card_save: 'Uložit',
                card_cancel: 'Zrušit',
            },
        });
        
        // Mockování Number.prototype.toLocaleString pro konzistentní výsledky v testovacím prostředí
        jest.spyOn(Number.prototype, 'toLocaleString').mockImplementation(function(this: number, locales, options) {
             const fixed = this.toFixed(options?.minimumFractionDigits || 2);
             return fixed.replace('.', ',');
        });
    });

    /**
     * Úklid po testu.
     */
    afterEach(() => {
        jest.restoreAllMocks();
    });

    /**
     * Testuje správné vykreslení názvu měny a částky.
     */
    test('renders currency name and amount correctly', () => {
        renderWithProviders(<WalletCard {...defaultProps} />);

        expect(screen.getByText('USD - Americký dolar')).toBeInTheDocument();
        expect(screen.getByText('100,50')).toBeInTheDocument();
    });

    /**
     * Testuje přepnutí do editačního režimu.
     */
    test('toggles edit mode', () => {
        renderWithProviders(<WalletCard {...defaultProps} />);

        const buttons = screen.getAllByRole('button');
        const editButton = buttons[0];

        fireEvent.click(editButton);

        expect(screen.getByDisplayValue('100.5')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /uložit/i })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /zrušit/i })).toBeInTheDocument();
    });

    /**
     * Testuje uložení nové hodnoty zůstatku.
     */
    test('calls onBalanceChange when save is clicked', () => {
        renderWithProviders(<WalletCard {...defaultProps} />);

        // Vstup do editace
        const buttons = screen.getAllByRole('button');
        fireEvent.click(buttons[0]);

        // Změna hodnoty
        const input = screen.getByDisplayValue('100.5');
        fireEvent.change(input, { target: { value: '200' } });

        // Uložení
        fireEvent.click(screen.getByRole('button', { name: /uložit/i }));

        expect(defaultProps.onBalanceChange).toHaveBeenCalledWith(200);
    });

    /**
     * Testuje zrušení editace bez uložení změn.
     */
    test('cancels edit mode', () => {
        renderWithProviders(<WalletCard {...defaultProps} />);

        // Vstup do editace
        const buttons = screen.getAllByRole('button');
        fireEvent.click(buttons[0]);

        // Změna hodnoty
        const input = screen.getByDisplayValue('100.5');
        fireEvent.change(input, { target: { value: '200' } });

        // Zrušení
        fireEvent.click(screen.getByRole('button', { name: /zrušit/i }));

        // Návrat k původní hodnotě
        expect(screen.getByText('100,50')).toBeInTheDocument();
        expect(screen.queryByDisplayValue('200')).not.toBeInTheDocument();
        expect(defaultProps.onBalanceChange).not.toHaveBeenCalled();
    });
});