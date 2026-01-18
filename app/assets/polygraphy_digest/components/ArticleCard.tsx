import React, { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Article } from '../types';
import { ExternalLink, Calendar, Building2, Eye, EyeOff } from 'lucide-react';
import { api } from '../services/api';

interface Props {
    article: Article;
}

const ArticleCard: React.FC<Props> = ({ article }) => {
    const { t, i18n } = useTranslation();
    const [status, setStatus] = useState(article.status || 'new');
    
    // Fallback formatting for date
    const formattedDate = new Date(article.publishedAt).toLocaleDateString(i18n.language === 'cs' ? 'cs-CZ' : i18n.language, {
        day: 'numeric',
        month: 'long',
        year: 'numeric'
    });

    const isDisabled = status === 'hidden';

    const handleToggleStatus = async (e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();
        const newStatus = isDisabled ? 'new' : 'hidden';
        try {
            setStatus(newStatus);
            await api.updateArticleStatus(article.id, newStatus);
        } catch (error) {
            console.error('Failed to update status', error);
            setStatus(status);
        }
    };

    return (
        <div className={`card h-100 shadow-sm border hover-lift transition-all ${isDisabled ? 'opacity-50 grayscale' : ''}`}>
            <div className="card-body d-flex flex-column">
                <div className="d-flex justify-content-between align-items-start mb-2">
                    <span className="badge bg-light text-primary border px-2 py-1 small d-flex align-items-center">
                        <Building2 size={12} className="me-1" />
                        {article.sourceName}
                    </span>
                    <span className="text-muted small d-flex align-items-center">
                        <Calendar size={12} className="me-1" />
                        {formattedDate}
                    </span>
                </div>
                
                <h5 className="card-title h6 fw-bold mb-2">
                    <a href={article.url} target="_blank" rel="noopener noreferrer" className="text-decoration-none text-dark stretched-link">
                        {article.title}
                    </a>
                </h5>
                
                <p className="card-text small text-muted flex-grow-1 mb-3" dangerouslySetInnerHTML={{ __html: article.summary }}>
                </p>

                <div className="mt-auto pt-3 border-top d-flex justify-content-between align-items-center">
                    <button 
                        className="btn btn-link text-muted p-0 me-3 position-relative z-2"
                        onClick={handleToggleStatus}
                        title={isDisabled ? t('action.enable') : t('action.disable')}
                    >
                        {isDisabled ? <Eye size={16} /> : <EyeOff size={16} />}
                    </button>
                    <span className="text-primary small fw-semibold d-flex align-items-center">
                        {t('more_link')} <ExternalLink size={14} className="ms-1" />
                    </span>
                </div>
            </div>
        </div>
    );
};

export default ArticleCard;