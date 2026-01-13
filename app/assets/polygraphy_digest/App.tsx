import React, { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { SearchProvider } from './context/SearchContext';
import SearchHeader from './components/SearchHeader';
import FacetsSidebar from './components/FacetsSidebar';
import ResultsList from './components/ResultsList';
import Dashboard from './pages/Dashboard';
import LanguageSwitcher from './components/LanguageSwitcher';
import { LayoutDashboard, Newspaper, Package, Settings } from 'lucide-react';

const App: React.FC = () => {
    const { t } = useTranslation();
    const [activeTab, setActiveTab] = useState<'dashboard' | 'search'>('dashboard');

    return (
        <SearchProvider>
            <div className="polygraphy-app min-vh-100 bg-light">
                {/* Navigation Sidebar (Mini) */}
                <div className="d-flex">
                    <div className="bg-dark text-white d-flex flex-column align-items-center py-4" style={{ width: '80px', minHeight: '100vh', position: 'fixed', zIndex: 100 }}>
                        <div className="mb-5">
                            <div className="bg-primary rounded-3 p-2 shadow">
                                <Package size={24} color="white" />
                            </div>
                        </div>
                        
                        <nav className="nav flex-column gap-4">
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
                            <button className="btn border-0 p-3 rounded-3 text-white-50 hover-text-white mt-auto" title={t('nav.settings')}>
                                <Settings size={24} />
                            </button>
                        </nav>
                    </div>

                    {/* Main Content Area */}
                    <main className="flex-grow-1" style={{ marginLeft: '80px' }}>
                        <header className="bg-white border-bottom py-3 px-4 mb-4 sticky-top shadow-sm" style={{ zIndex: 90 }}>
                            <div className="container-fluid">
                                <div className="row align-items-center">
                                    <div className="col">
                                        <h1 className="h4 mb-0 fw-bold text-dark">
                                            {activeTab === 'dashboard' ? t('dashboard_title') : t('app_title')}
                                        </h1>
                                        <span className="small text-muted">{t('app_subtitle')}</span>
                                    </div>
                                    <div className="col-auto">
                                        <LanguageSwitcher />
                                    </div>
                                </div>
                            </div>
                        </header>

                        <div className="container-fluid px-4 pb-5">
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
                    </main>
                </div>
            </div>
        </SearchProvider>
    );
};

export default App;
