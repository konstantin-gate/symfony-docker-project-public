import React, {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {SearchProvider} from './context/SearchContext';
import {SettingsProvider} from './context/SettingsContext';
import SearchHeader from './components/SearchHeader';
import FacetsSidebar from './components/FacetsSidebar';
import ResultsList from './components/ResultsList';
import Dashboard from './pages/Dashboard';
import SettingsPage from './pages/SettingsPage';
import AppHeader from './components/AppHeader';
import {LayoutDashboard, Newspaper, Package, Settings} from 'lucide-react';

const App: React.FC = () => {
    const {t} = useTranslation();
    const [activeTab, setActiveTab] = useState<'dashboard' | 'search' | 'settings'>('dashboard');

    const getTitle = () => {
        switch (activeTab) {
            case 'dashboard': return t('dashboard_title');
            case 'settings': return t('nav.settings');
            default: return t('app_title');
        }
    };

    return (
        <SettingsProvider>
            <SearchProvider>
                <div className="polygraphy-app min-vh-100 bg-body" data-polygraphy-app>
                    <AppHeader title={getTitle()}/>

                    <main className="container-fluid px-0 pb-5 max-w-7xl mx-auto" data-main-content>
                        <div className="d-flex gap-4 align-items-start">
                            {/* Navigation Sidebar (Moved inside container) */}
                            <div
                                className="bg-dark text-white d-flex flex-column align-items-center py-4 rounded-3 shadow sticky-top flex-shrink-0"
                                style={{width: '60px', minWidth: '60px', top: '110px', zIndex: 80}}
                                data-navigation-sidebar
                            >
                                <div className="mb-4">
                                    <div
                                        className="bg-primary rounded-3 shadow d-flex align-items-center justify-content-center"
                                        style={{width: '40px', height: '40px'}}>
                                        <Package size={20} color="white"/>
                                    </div>
                                </div>

                                <nav className="nav flex-column gap-3">
                                    <button
                                        onClick={() => setActiveTab('dashboard')}
                                        className={`btn border-0 p-0 rounded-3 transition-all d-flex align-items-center justify-content-center ${activeTab === 'dashboard' ? 'bg-primary text-white shadow' : 'text-white-50 hover-text-white'}`}
                                        style={{width: '40px', height: '40px'}}
                                        title={t('nav.dashboard')}
                                    >
                                        <LayoutDashboard size={20}/>
                                    </button>
                                    <button
                                        onClick={() => setActiveTab('search')}
                                        className={`btn border-0 p-0 rounded-3 transition-all d-flex align-items-center justify-content-center ${activeTab === 'search' ? 'bg-primary text-white shadow' : 'text-white-50 hover-text-white'}`}
                                        style={{width: '40px', height: '40px'}}
                                        title={t('nav.search')}
                                    >
                                        <Newspaper size={20}/>
                                    </button>
                                    <button
                                        onClick={() => setActiveTab('settings')}
                                        className={`btn border-0 p-0 rounded-3 transition-all d-flex align-items-center justify-content-center ${activeTab === 'settings' ? 'bg-primary text-white shadow' : 'text-white-50 hover-text-white'}`}
                                        style={{width: '40px', height: '40px'}}
                                        title={t('nav.settings')}
                                    >
                                        <Settings size={20}/>
                                    </button>
                                </nav>
                            </div>
                            {/* Main Content Area */}
                            <div className="flex-grow-1 ps-3 pe-0 overflow-x-hidden" style={{minWidth: 0}}>
                                {activeTab === 'dashboard' && (
                                    <Dashboard onNavigateToSearch={() => setActiveTab('search')} />
                                )}
                                {activeTab === 'search' && (
                                    <div className="search-interface">
                                        <SearchHeader/>
                                        <div className="row g-4">
                                            <div className="col-lg-3" data-filters-column>
                                                <FacetsSidebar/>
                                            </div>
                                            <div className="col-lg-9">
                                                <ResultsList/>
                                            </div>
                                        </div>
                                    </div>
                                )}
                                {activeTab === 'settings' && (
                                    <SettingsPage />
                                )}
                            </div>
                        </div>
                    </main>
                </div>
            </SearchProvider>
        </SettingsProvider>
    );
};

export default App;