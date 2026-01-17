import React, { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { api } from '../services/api';
import { BarChart3, TrendingUp, Newspaper, PackageSearch, TrendingDown, Search } from 'lucide-react';
import { useSearch } from '../context/SearchContext';

interface DashboardProps {
    onNavigateToSearch: () => void;
}

const Dashboard: React.FC<DashboardProps> = ({ onNavigateToSearch }) => {
    const { t } = useTranslation();
    const { setQuery, performSearch } = useSearch();
    const [stats, setStats] = useState<any>(null);
    const [isLoading, setIsLoading] = useState(true);
    const [localQuery, setLocalQuery] = useState('');

    useEffect(() => {
        const fetchStats = async () => {
            try {
                const data = await api.getStats({ page: 1, limit: 0, filters: {}, sort: {} });
                setStats(data);
            } catch (err) {
                console.error('Failed to fetch stats', err);
            } finally {
                setIsLoading(false);
            }
        };
        fetchStats();
    }, []);

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        if (localQuery.trim()) {
            setQuery(localQuery);
            onNavigateToSearch();
        }
    };

    if (isLoading) return <div className="text-center py-5"><div className="spinner-border text-primary" /></div>;

    // Data Extraction
    const sourcesCount = stats?.aggregations?.sources?.buckets?.length || 0;
    const totalArticles = stats?.total || 0;

    // Trend Calculation
    const calculateTrend = () => {
        const buckets = stats?.aggregations?.weekly_trend?.buckets;
        if (!buckets) return 0;

        const current = buckets.current_week?.doc_count || 0;
        const previous = buckets.last_week?.doc_count || 0;

        if (previous === 0) {
            return current > 0 ? 100 : 0;
        }

        return Math.round(((current - previous) / previous) * 100);
    };

    const trend = calculateTrend();
    const trendLabel = trend > 0 ? `+${trend}%` : `${trend}%`;
    const isPositiveTrend = trend >= 0;

    return (
        <div className="dashboard" data-dashboard-page>
            <div className="row g-4 mb-4" data-stats-grid>
                <div className="col-md-4" data-stats-col="sources">
                    <div className="card border-0 shadow-sm bg-primary text-white">
                        <div className="card-body">
                            <div className="d-flex justify-content-between align-items-center">
                                <div>
                                    <div className="small opacity-75 text-uppercase fw-bold mb-1">{t('active_sources')}</div>
                                    <div className="h3 mb-0 fw-bold">{sourcesCount}</div>
                                </div>
                                <BarChart3 size={32} className="opacity-50" />
                            </div>
                        </div>
                    </div>
                </div>
                <div className="col-md-4" data-stats-col="trend">
                    <div className={`card border-0 shadow-sm text-white ${isPositiveTrend ? 'bg-success' : 'bg-danger'}`}>
                        <div className="card-body">
                            <div className="d-flex justify-content-between align-items-center">
                                <div>
                                    <div className="small opacity-75 text-uppercase fw-bold mb-1">{t('trend_week')}</div>
                                    <div className="h3 mb-0 fw-bold">{trendLabel}</div>
                                </div>
                                {isPositiveTrend ? <TrendingUp size={32} className="opacity-50" /> : <TrendingDown size={32} className="opacity-50" />}
                            </div>
                        </div>
                    </div>
                </div>
                <div className="col-md-4" data-stats-col="total">
                    <div className="card border-0 shadow-sm bg-info text-white">
                        <div className="card-body">
                            <div className="d-flex justify-content-between align-items-center">
                                <div>
                                    <div className="small opacity-75 text-uppercase fw-bold mb-1">{t('total_articles')}</div>
                                    <div className="h3 mb-0 fw-bold">{totalArticles.toLocaleString()}</div>
                                </div>
                                <Newspaper size={32} className="opacity-50" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div className="row g-4" data-dashboard-content>
                <div className="col-lg-8" data-stats-distribution>
                    <div className="card border-0 shadow-sm h-100">
                        <div className="card-header bg-white py-3">
                            <h6 className="mb-0 fw-bold">{t('source_distribution')}</h6>
                        </div>
                        <div className="card-body">
                            <div className="table-responsive">
                                <table className="table table-hover align-middle mb-0">
                                    <thead className="table-light">
                                        <tr className="small text-uppercase text-muted">
                                            <th>{t('filters.sources')}</th>
                                            <th className="text-end">{t('total_articles')}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {stats?.aggregations?.sources?.buckets?.map((bucket: any) => (
                                            <tr key={bucket.key}>
                                                <td className="fw-semibold text-dark">{bucket.key}</td>
                                                <td className="text-end">
                                                    <span className="badge bg-light text-dark border">{bucket.doc_count}</span>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div className="col-lg-4" data-stats-quick-search>
                    <div className="card border-0 shadow-sm h-100" data-quick-search-block>
                        <div className="card-header bg-white py-3">
                            <h6 className="mb-0 fw-bold">{t('quick_search')}</h6>
                        </div>
                        <div className="card-body text-center d-flex flex-column justify-content-center">
                            <PackageSearch size={48} className="text-primary mx-auto mb-3 opacity-25" />
                            <p className="text-muted small mb-4">{t('quick_search_desc')}</p>
                            
                            <form onSubmit={handleSearch} className="w-100 px-3">
                                <div className="input-group">
                                    <input 
                                        type="text" 
                                        className="form-control search-input-no-focus" 
                                        placeholder={t('search_placeholder') || "Search..."}
                                        value={localQuery}
                                        onChange={(e) => setLocalQuery(e.target.value)}
                                    />
                                    <button className="btn btn-primary" type="submit">
                                        <Search size={18} />
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default Dashboard;
