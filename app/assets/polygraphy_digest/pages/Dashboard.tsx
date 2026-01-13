import React, { useEffect, useState } from 'react';
import { api } from '../services/api';
import { BarChart3, TrendingUp, Newspaper, PackageSearch } from 'lucide-react';

const Dashboard: React.FC = () => {
    const [stats, setStats] = useState<any>(null);
    const [isLoading, setIsLoading] = useState(true);

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

    if (isLoading) return <div className="text-center py-5"><div className="spinner-border text-primary" /></div>;

    const sourcesCount = stats?.sources?.buckets?.length || 0;

    return (
        <div className="dashboard">
            <div className="row g-4 mb-4">
                <div className="col-md-4">
                    <div className="card border-0 shadow-sm bg-primary text-white">
                        <div className="card-body">
                            <div className="d-flex justify-content-between align-items-center">
                                <div>
                                    <div className="small opacity-75 text-uppercase fw-bold mb-1">Aktivní zdroje</div>
                                    <div className="h3 mb-0 fw-bold">{sourcesCount}</div>
                                </div>
                                <BarChart3 size={32} className="opacity-50" />
                            </div>
                        </div>
                    </div>
                </div>
                <div className="col-md-4">
                    <div className="card border-0 shadow-sm bg-success text-white">
                        <div className="card-body">
                            <div className="d-flex justify-content-between align-items-center">
                                <div>
                                    <div className="small opacity-75 text-uppercase fw-bold mb-1">Trend týdne</div>
                                    <div className="h3 mb-0 fw-bold">+12%</div>
                                </div>
                                <TrendingUp size={32} className="opacity-50" />
                            </div>
                        </div>
                    </div>
                </div>
                <div className="col-md-4">
                    <div className="card border-0 shadow-sm bg-info text-white">
                        <div className="card-body">
                            <div className="d-flex justify-content-between align-items-center">
                                <div>
                                    <div className="small opacity-75 text-uppercase fw-bold mb-1">Celkem článků</div>
                                    <div className="h3 mb-0 fw-bold">1,240</div>
                                </div>
                                <Newspaper size={32} className="opacity-50" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div className="row g-4">
                <div className="col-lg-8">
                    <div className="card border-0 shadow-sm h-100">
                        <div className="card-header bg-white py-3">
                            <h6 className="mb-0 fw-bold">Distribuce zdrojů</h6>
                        </div>
                        <div className="card-body">
                            <div className="table-responsive">
                                <table className="table table-hover align-middle mb-0">
                                    <thead className="table-light">
                                        <tr className="small text-uppercase text-muted">
                                            <th>Zdroj</th>
                                            <th className="text-end">Počet článků</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {stats?.sources?.buckets?.map((bucket: any) => (
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
                <div className="col-lg-4">
                    <div className="card border-0 shadow-sm h-100">
                        <div className="card-header bg-white py-3">
                            <h6 className="mb-0 fw-bold">Rychlé hledání</h6>
                        </div>
                        <div className="card-body text-center d-flex flex-column justify-content-center">
                            <PackageSearch size={48} className="text-primary mx-auto mb-3 opacity-25" />
                            <p className="text-muted small mb-4">Najděte nejlevnější tiskové služby napříč trhem během několika sekund.</p>
                            <button className="btn btn-outline-primary rounded-pill">Spustit vyhledávač</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default Dashboard;
