export interface Article {
    id: string;
    title: string;
    summary: string;
    content: string;
    url: string;
    publishedAt: string;
    sourceName: string;
}

export interface Product {
    id: string;
    name: string;
    description: string;
    price: number | string;
    currency: string;
    articleId?: string;
}

export interface SearchAggregations {
    [key: string]: {
        buckets?: Array<{
            key: string;
            doc_count: number;
        }>;
        value?: number;
        min?: number;
        max?: number;
        avg?: number;
        sum?: number;
    };
}

export interface SearchResult<T> {
    items: T[];
    total: number;
    aggregations: SearchAggregations;
    page: number;
    totalPages: number;
}

export interface SearchCriteria {
    query?: string;
    page: number;
    limit: number;
    filters: Record<string, any>;
    sort: Record<string, 'asc' | 'desc'>;
}
