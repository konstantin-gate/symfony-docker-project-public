import React, { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { SearchProvider } from './context/SearchContext';
import SearchHeader from './components/SearchHeader';
import FacetsSidebar from './components/FacetsSidebar';
import ResultsList from './components/ResultsList';
import Dashboard from './pages/Dashboard';
import AppHeader from './components/AppHeader';
import { LayoutDashboard, Newspaper, Package, Settings } from 'lucide-react';

const App: React.FC = () => {
    const { t } = useTranslation();
    const [activeTab, setActiveTab] = useState<'dashboard' | 'search'>('dashboard');

    return (
        <SearchProvider>
            <div className="polygraphy-app min-vh-100 bg-light">
                <AppHeader title={activeTab === 'dashboard' ? t('dashboard_title') : t('app_title')} />

                <main className="container-fluid px-0 pb-5 max-w-7xl mx-auto">
                    <div className="d-flex gap-4 align-items-start">
                        {/* Navigation Sidebar (Moved inside container) */}
                        <div className="bg-dark text-white d-flex flex-column align-items-center py-4 rounded-3 shadow sticky-top" style={{ width: '80px', top: '100px', zIndex: 80 }}>
                            <div className="mb-4">
                                <div className="bg-primary rounded-3 p-2 shadow">
                                    <Package size={24} color="white" />
                                </div>
                            </div>
                            
                            <nav className="nav flex-column gap-3">
                                <button 
                                    onClick={() => setActiveTab('dashboard')}
                                    className={`btn border-0 p-3 rounded-3 transition-all ${activeTab === 'dashboard' ? 'bg-primary text-white shadow' : 'text-white-50 hover-text-white'}`}
                                    title={t('nav.dashboard')}
                                >
                                    <LayoutDashboard size={24} />
                                </button>
                                <button 
                                    onClick={() => setActiveTab('search')}
                                    className={`btn border-0 p-3 rounded-3 transition-all ${activeTab === 'search' ? 'bg-primary text-white shadow' : 'text-white-50 hover-text-white'}`}
                                    title={t('nav.search')}
                                >
                                    <Newspaper size={24} />
                                </button>
                                <button className="btn border-0 p-3 rounded-3 text-white-50 hover-text-white mt-3" title={t('nav.settings')}>
                                    <Settings size={24} />
                                </button>
                            </nav>
                        </div>

                        {/* Main Content Area */}
                        <div className="flex-grow-1" style={{ minWidth: 0 }}>
                            {activeTab === 'dashboard' ? (
                                <Dashboard />
                            ) : (
                                <div className="search-interface">
                                    <SearchHeader />
                                    <div className="row g-4">
                                        <div className="col-lg-3">
                                            <FacetsSidebar />
                                        </div>
                                        <div className="col-lg-9">
                                            <ResultsList />
                                        </div>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </main>
            </div>
        </SearchProvider>
    );
};

export default App;
