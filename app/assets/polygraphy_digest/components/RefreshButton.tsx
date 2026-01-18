import React, { useState } from 'react';
import { RefreshCw, Loader2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface RefreshButtonProps {
    onRefreshComplete?: () => void;
    className?: string;
}

/**
 * Komponenta tlačítka pro ruční spuštění parsování všech zdrojů.
 */
export const RefreshButton: React.FC<RefreshButtonProps> = ({ onRefreshComplete, className }) => {
    const [isLoading, setIsLoading] = useState<boolean>(false);
    const { t } = useTranslation();

    const handleRefresh = async () => {
        if (isLoading) return;

        setIsLoading(true);
        try {
            const response = await fetch('/api/polygraphy/crawl', {
                method: 'POST',
            });

            if (!response.ok) {
                throw new Error('Failed to refresh data');
            }

            if (onRefreshComplete) {
                onRefreshComplete();
            } else {
                // Výchozí chování: obnovit stránku pro zobrazení nových dat
                window.location.reload();
            }
        } catch (error) {
            console.error('Error during crawl:', error);
            alert(t('error.refresh_failed'));
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <button
            onClick={handleRefresh}
            disabled={isLoading}
            className={`btn btn-outline-primary d-flex align-items-center justify-content-center gap-2 ${className || ''}`}
            title={t('actions.refresh_sources')}
            data-refresh-button
        >
            {isLoading ? (
                <Loader2 className="animate-spin" size={18} />
            ) : (
                <RefreshCw size={18} />
            )}
            <span>
                {isLoading ? t('actions.refreshing') : t('actions.refresh')}
            </span>
        </button>
    );
};
