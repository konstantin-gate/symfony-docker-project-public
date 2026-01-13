import { SearchCriteria, SearchResult, Article, Product } from '../types';

const API_BASE_URL = '/api/polygraphy';

async function handleResponse<T>(response: Response): Promise<T> {
    if (!response.ok) {
        const errorData = await response.json().catch(() => ({}));
        throw new Error(errorData.error || `HTTP error! status: ${response.status}`);
    }
    return response.json();
}

export const api = {
    async searchArticles(criteria: SearchCriteria): Promise<SearchResult<Article>> {
        const params = new URLSearchParams({
            q: criteria.query || '',
            page: criteria.page.toString(),
            limit: criteria.limit.toString(),
        });

        Object.entries(criteria.filters).forEach(([key, value]) => {
            if (value !== undefined && value !== null) {
                params.append(`filters[${key}]`, value.toString());
            }
        });

        Object.entries(criteria.sort).forEach(([key, value]) => {
            params.append(`sort[${key}]`, value);
        });

        const response = await fetch(`${API_BASE_URL}/articles?${params.toString()}`);
        return handleResponse<SearchResult<Article>>(response);
    },

    async searchProducts(criteria: SearchCriteria): Promise<SearchResult<Product>> {
        const params = new URLSearchParams({
            q: criteria.query || '',
            page: criteria.page.toString(),
            limit: criteria.limit.toString(),
        });

        Object.entries(criteria.filters).forEach(([key, value]) => {
            if (value !== undefined && value !== null) {
                params.append(`filters[${key}]`, value.toString());
            }
        });

        Object.entries(criteria.sort).forEach(([key, value]) => {
            params.append(`sort[${key}]`, value);
        });

        const response = await fetch(`${API_BASE_URL}/products?${params.toString()}`);
        return handleResponse<SearchResult<Product>>(response);
    },

    async getSuggestions(query: string): Promise<string[]> {
        if (query.length < 3) return [];
        const response = await fetch(`${API_BASE_URL}/suggest?q=${encodeURIComponent(query)}`);
        return handleResponse<string[]>(response);
    },

    async getStats(criteria: SearchCriteria): Promise<any> {
        const params = new URLSearchParams({
            q: criteria.query || '',
        });
        
        Object.entries(criteria.filters).forEach(([key, value]) => {
            params.append(`filters[${key}]`, value.toString());
        });

        const response = await fetch(`${API_BASE_URL}/stats?${params.toString()}`);
        return handleResponse<any>(response);
    }
};
