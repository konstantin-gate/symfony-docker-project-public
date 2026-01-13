import React from 'react';
import { useTranslation } from 'react-i18next';
import { useSearch } from '../context/SearchContext';
import ArticleCard from './ArticleCard';
import ProductCard from './ProductCard';
import { Loader2, AlertCircle } from 'lucide-react';

const ResultsList: React.FC = () => {
    const { t } = useTranslation();
    const { articleResults, productResults, isLoading, error, searchMode } = useSearch();

    if (isLoading && !articleResults && !productResults) {
        return (
            <div className="text-center py-5">
                <Loader2 className="animate-spin text-primary mx-auto mb-3" size={40} />
                <p className="text-muted">{t('loading')}</p>
            </div>
        );
    }

    if (error) {
        return (
            <div className="alert alert-danger d-flex align-items-center" role="alert">
                <AlertCircle size={20} className="me-2" />
                {error}
            </div>
        );
    }

    const results = searchMode === 'articles' ? articleResults : productResults;

    if (!results || results.items.length === 0) {
        return (
            <div className="text-center py-5 bg-light rounded shadow-sm border border-dashed">
                <p className="mb-0 text-muted">{t('no_results')}</p>
            </div>
        );
    }

    return (
        <div className="results-container">
            <div className="d-flex justify-content-between align-items-center mb-4">
                <h2 className="h5 mb-0 fw-bold">
                    {searchMode === 'articles' 
                        ? t('found_articles', { count: results.total }) 
                        : t('found_products', { count: results.total })}
                </h2>
                <div className="small text-muted">
                    {t('page_info', { page: results.page, totalPages: results.totalPages })}
                </div>
            </div>

            <div className="row g-4">
                {results.items.map((item: any) => (
                    <div key={item.id} className="col-md-6 col-lg-4">
                        {searchMode === 'articles' ? (
                            <ArticleCard article={item} />
                        ) : (
                            <ProductCard product={item} />
                        )}
                    </div>
                ))}
            </div>

            {/* Simple Pagination */}
            {results.totalPages > 1 && (
                <div className="d-flex justify-content-center mt-5">
                    <nav aria-label="Page navigation">
                        <ul className="pagination shadow-sm">
                            <li className={`page-item ${results.page <= 1 ? 'disabled' : ''}`}>
                                <button className="page-link px-3 py-2">{t('prev_page')}</button>
                            </li>
                            <li className="page-item active"><span className="page-link px-3 py-2">{results.page}</span></li>
                            <li className={`page-item ${results.page >= results.totalPages ? 'disabled' : ''}`}>
                                <button className="page-link px-3 py-2">{t('next_page')}</button>
                            </li>
                        </ul>
                    </nav>
                </div>
            )}
        </div>
    );
};

export default ResultsList;