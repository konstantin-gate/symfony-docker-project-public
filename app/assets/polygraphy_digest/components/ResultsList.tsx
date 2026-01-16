import React from 'react';
import { useTranslation } from 'react-i18next';
import { useSearch } from '../context/SearchContext';
import ArticleCard from './ArticleCard';
import ProductCard from './ProductCard';
import { Loader2, AlertCircle } from 'lucide-react';

const ResultsList: React.FC = () => {
    const { t } = useTranslation();
    const { articleResults, productResults, isLoading, error, searchMode, setPage } = useSearch();

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

            {/* Pagination */}
            {results.totalPages > 1 && (
                <div className="d-flex justify-content-center mt-5">
                    <nav aria-label="Page navigation">
                        <ul className="pagination pagination-sm shadow-sm">
                            <li className={`page-item ${results.page <= 1 ? 'disabled' : ''}`}>
                                <button 
                                    className="page-link"
                                    onClick={() => setPage(results.page - 1)}
                                    disabled={results.page <= 1}
                                >
                                    {t('prev_page')}
                                </button>
                            </li>
                            
                            {(() => {
                                const pages = [];
                                const maxVisibleButtons = 5;
                                let startPage = Math.max(1, results.page - Math.floor(maxVisibleButtons / 2));
                                let endPage = Math.min(results.totalPages, startPage + maxVisibleButtons - 1);

                                if (endPage - startPage + 1 < maxVisibleButtons) {
                                    startPage = Math.max(1, endPage - maxVisibleButtons + 1);
                                }

                                if (startPage > 1) {
                                     pages.push(
                                        <li key={1} className="page-item">
                                            <button className="page-link" onClick={() => setPage(1)}>1</button>
                                        </li>
                                    );
                                    if (startPage > 2) {
                                        pages.push(<li key="ellipsis-start" className="page-item disabled"><span className="page-link">...</span></li>);
                                    }
                                }

                                for (let i = startPage; i <= endPage; i++) {
                                    pages.push(
                                        <li key={i} className={`page-item ${i === results.page ? 'active' : ''}`}>
                                            <button className="page-link" onClick={() => setPage(i)}>{i}</button>
                                        </li>
                                    );
                                }

                                if (endPage < results.totalPages) {
                                    if (endPage < results.totalPages - 1) {
                                        pages.push(<li key="ellipsis-end" className="page-item disabled"><span className="page-link">...</span></li>);
                                    }
                                    pages.push(
                                        <li key={results.totalPages} className="page-item">
                                            <button className="page-link" onClick={() => setPage(results.totalPages)}>{results.totalPages}</button>
                                        </li>
                                    );
                                }
                                return pages;
                            })()}

                            <li className={`page-item ${results.page >= results.totalPages ? 'disabled' : ''}`}>
                                <button 
                                    className="page-link"
                                    onClick={() => setPage(results.page + 1)}
                                    disabled={results.page >= results.totalPages}
                                >
                                    {t('next_page')}
                                </button>
                            </li>
                        </ul>
                    </nav>
                </div>
            )}
        </div>
    );
};

export default ResultsList;