import React, { createContext, useContext, useState, useCallback, ReactNode, useEffect, useRef } from 'react';
import { SearchCriteria, SearchResult, Article, Product } from '../types';
import { api } from '../services/api';

interface SearchContextType {
    query: string;
    setQuery: (query: string) => void;
    page: number;
    setPage: (page: number) => void;
    filters: Record<string, any>;
    setFilters: (filters: Record<string, any>) => void;
    articleResults: SearchResult<Article> | null;
    productResults: SearchResult<Product> | null;
    isLoading: boolean;
    error: string | null;
    searchMode: 'articles' | 'products';
    setSearchMode: (mode: 'articles' | 'products') => void;
    performSearch: () => Promise<void>;
}

const SearchContext = createContext<SearchContextType | undefined>(undefined);

export const SearchProvider: React.FC<{ children: ReactNode }> = ({ children }) => {
    const [query, setQuery] = useState('');
    const [page, setPage] = useState(1);
    const [filters, setFilters] = useState<Record<string, any>>({});
    const [searchMode, setSearchMode] = useState<'articles' | 'products'>('articles');
    const [articleResults, setArticleResults] = useState<SearchResult<Article> | null>(null);
    const [productResults, setProductResults] = useState<SearchResult<Product> | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    // Ref to prevent initial search if we want to wait for dashboard or manual trigger
    // But for this module, it's better to load initial data.
    const isInitialMount = useRef(true);

    const handleSetQuery = (newQuery: string) => {
        setQuery(newQuery);
        setPage(1);
    };

    const handleSetFilters = (newFilters: Record<string, any>) => {
        setFilters(newFilters);
        setPage(1);
    };

    const handleSetSearchMode = (mode: 'articles' | 'products') => {
        setSearchMode(mode);
        setPage(1);
    };

    const performSearch = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const criteria: SearchCriteria = {
                query,
                page,
                limit: 12,
                filters,
                sort: searchMode === 'articles' ? { published_at: 'desc' } : {},
            };

            if (searchMode === 'articles') {
                const results = await api.searchArticles(criteria);
                setArticleResults(results);
            } else {
                const results = await api.searchProducts(criteria);
                setProductResults(results);
            }
        } catch (err: any) {
            setError(err.message || 'An error occurred during search');
        } finally {
            setIsLoading(false);
        }
    }, [query, page, filters, searchMode]);

    // Reactive search: triggers when filters, page, search mode or query change
    useEffect(() => {
        // We can skip the very first mount if needed, 
        // but usually we want to see initial results in the search tab.
        performSearch();
    }, [filters, page, searchMode, query]);

    return (
        <SearchContext.Provider value={{
            query, setQuery: handleSetQuery,
            page, setPage,
            filters, setFilters: handleSetFilters,
            articleResults, productResults,
            isLoading, error,
            searchMode, setSearchMode: handleSetSearchMode,
            performSearch
        }}>
            {children}
        </SearchContext.Provider>
    );
};

export const useSearch = () => {
    const context = useContext(SearchContext);
    if (context === undefined) {
        throw new Error('useSearch must be used within a SearchProvider');
    }
    return context;
};