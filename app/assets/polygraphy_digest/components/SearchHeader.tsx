import React, { useState, useEffect, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { useSearch } from '../context/SearchContext';
import { api } from '../services/api';
import { Search, Loader2 } from 'lucide-react';
import { debounce } from 'lodash';

const SearchHeader: React.FC = () => {
    const { t } = useTranslation();
    const { query, setQuery, performSearch, isLoading } = useSearch();
    const [suggestions, setSuggestions] = useState<string[]>([]);
    const [showSuggestions, setShowSuggestions] = useState(false);

    const fetchSuggestions = useCallback(
        debounce(async (q: string) => {
            if (q.length >= 3) {
                const results = await api.getSuggestions(q);
                setSuggestions(results);
                setShowSuggestions(true);
            } else {
                setSuggestions([]);
                setShowSuggestions(false);
            }
        }, 300),
        []
    );

    const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const val = e.target.value;
        setQuery(val);
        fetchSuggestions(val);
    };

    const handleSuggestionClick = (suggestion: string) => {
        setQuery(suggestion);
        setSuggestions([]);
        setShowSuggestions(false);
        setTimeout(performSearch, 0);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setShowSuggestions(false);
        performSearch();
    };

    return (
        <div className="search-header mb-4">
            <form onSubmit={handleSubmit} className="position-relative">
                <div className="input-group input-group-lg shadow-sm">
                    <span className="input-group-text bg-white border-end-0">
                        {isLoading ? <Loader2 className="animate-spin text-primary" size={20} /> : <Search size={20} className="text-muted" />}
                    </span>
                    <input
                        type="text"
                        className="form-control border-start-0 ps-0"
                        placeholder={t('search_placeholder')}
                        value={query}
                        onChange={handleInputChange}
                        onBlur={() => setTimeout(() => setShowSuggestions(false), 200)}
                    />
                    <button className="btn btn-primary px-4" type="submit">{t('search_button')}</button>
                </div>

                {showSuggestions && suggestions.length > 0 && (
                    <div className="list-group position-absolute w-100 shadow-lg mt-1" style={{ zIndex: 1000 }}>
                        {suggestions.map((s, idx) => (
                            <button
                                key={idx}
                                type="button"
                                className="list-group-item list-group-item-action"
                                onClick={() => handleSuggestionClick(s)}
                            >
                                {s}
                            </button>
                        ))}
                    </div>
                )}
            </form>
        </div>
    );
};

export default SearchHeader;