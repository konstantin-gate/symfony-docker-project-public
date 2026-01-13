import React from 'react';
import { Product } from '../types';
import { ShoppingCart, Tag } from 'lucide-react';

interface Props {
    product: Product;
}

const ProductCard: React.FC<Props> = ({ product }) => {
    return (
        <div className="card h-100 shadow-sm border-0 transition-all">
            <div className="card-body">
                <div className="d-flex justify-content-between align-items-center mb-2">
                    <span className="badge bg-success-soft text-success px-2 py-1 small fw-bold">
                        {product.price} {product.currency}
                    </span>
                    <Tag size={16} className="text-muted" />
                </div>
                
                <h5 className="card-title h6 fw-bold mb-2 text-dark">
                    {product.name}
                </h5>
                
                <p className="card-text small text-muted mb-0">
                    {product.description}
                </p>
            </div>
            <div className="card-footer bg-transparent border-0 pb-3">
                <button className="btn btn-outline-primary btn-sm w-100 d-flex align-items-center justify-content-center">
                    <ShoppingCart size={14} className="me-2" />
                    Koupit
                </button>
            </div>
        </div>
    );
};

export default ProductCard;
