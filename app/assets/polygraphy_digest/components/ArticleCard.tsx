import React from 'react';
import { Article } from '../types';
import { ExternalLink, Calendar, Building2 } from 'lucide-react';

interface Props {
    article: Article;
}

const ArticleCard: React.FC<Props> = ({ article }) => {
    const formattedDate = new Date(article.publishedAt).toLocaleDateString('cs-CZ', {
        day: 'numeric',
        month: 'long',
        year: 'numeric'
    });

    return (
        <div className="card h-100 shadow-sm border-0 hover-lift transition-all">
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

                <div className="mt-auto pt-3 border-top d-flex justify-content-end">
                    <span className="text-primary small fw-semibold d-flex align-items-center">
                        Více <ExternalLink size={14} className="ms-1" />
                    </span>
                </div>
            </div>
        </div>
    );
};

export default ArticleCard;
