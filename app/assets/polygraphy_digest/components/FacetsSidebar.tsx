import React from 'react';
import { useTranslation } from 'react-i18next';
import { useSearch } from '../context/SearchContext';
import { Filter, Calendar, Tag, CreditCard } from 'lucide-react';

const FacetsSidebar: React.FC = () => {
    const { t } = useTranslation();
    const { articleResults, productResults, searchMode, filters, setFilters, performSearch } = useSearch();

    const aggregations = searchMode === 'articles' ? articleResults?.aggregations : productResults?.aggregations;

    const handleFilterChange = (key: string, value: any) => {
        const newFilters = { ...filters };
        if (newFilters[key] === value) {
            delete newFilters[key];
        } else {
            newFilters[key] = value;
        }
        setFilters(newFilters);
    };

    return (
        <div className="card shadow-sm border-0 sticky-top" style={{ top: '20px' }}>
            <div className="card-header bg-white border-0 py-3">
                <h5 className="card-title mb-0 d-flex align-items-center">
                    <Filter size={18} className="me-2 text-primary" />
                    {t('filters.title')}
                </h5>
            </div>
            <div className="card-body pt-0">
                {/* Sources Facet */}
                {aggregations?.sources && (
                    <div className="mb-4">
                        <label className="form-label fw-bold d-flex align-items-center small text-uppercase text-muted mb-3">
                            <Tag size={14} className="me-2" />
                            {t('filters.sources')}
                        </label>
                        <div className="list-group list-group-flush border rounded overflow-hidden">
                            {aggregations.sources.buckets?.map((bucket) => (
                                <button
                                    key={bucket.key}
                                    className={`list-group-item list-group-item-action border-0 d-flex justify-content-between align-items-center ${filters.source_id === bucket.key ? 'bg-primary text-white' : ''}`}
                                    onClick={() => handleFilterChange('source_id', bucket.key)}
                                >
                                    <span className="text-truncate small">{bucket.key}</span>
                                    <span className={`badge rounded-pill ${filters.source_id === bucket.key ? 'bg-white text-primary' : 'bg-light text-muted'}`}>
                                        {bucket.doc_count}
                                    </span>
                                </button>
                            ))}
                        </div>
                    </div>
                )}

                {/* Date Filter Placeholder */}
                <div className="mb-4">
                    <label className="form-label fw-bold d-flex align-items-center small text-uppercase text-muted mb-3">
                        <Calendar size={14} className="me-2" />
                        {t('filters.period')}
                    </label>
                    <select className="form-select form-select-sm" onChange={(e) => handleFilterChange('date_range', e.target.value)}>
                        <option value="">{t('filters.all_dates')}</option>
                        <option value="today">{t('filters.today')}</option>
                        <option value="week">{t('filters.week')}</option>
                        <option value="month">{t('filters.month')}</option>
                    </select>
                </div>

                {/* Price Stats (for products) */}
                {searchMode === 'products' && aggregations?.price_stats && (
                    <div className="mb-4">
                        <label className="form-label fw-bold d-flex align-items-center small text-uppercase text-muted mb-3">
                            <CreditCard size={14} className="me-2" />
                            {t('filters.price')}
                        </label>
                        <div className="px-2 small text-muted">
                            <div>{t('filters.min')}: {Math.round(aggregations.price_stats.min || 0)}</div>
                            <div>{t('filters.max')}: {Math.round(aggregations.price_stats.max || 0)}</div>
                            <div>{t('filters.avg')}: {Math.round(aggregations.price_stats.avg || 0)}</div>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
};

export default FacetsSidebar;