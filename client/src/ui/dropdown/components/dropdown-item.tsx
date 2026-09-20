import type { FC, MouseEvent } from 'react';
import { Link } from 'react-router';

import { DROPDOWN_ITEM_BASE_CLASSES } from '../constants/dropdown-item-styles';
import type { DropdownItemProps } from '../props/dropdown-item-props';

export const DropdownItem: FC<DropdownItemProps> = ({ tag = 'button', to, onClick, onItemClick, className = '', children }) => {
    const combinedClasses = `${DROPDOWN_ITEM_BASE_CLASSES} ${className}`.trim();

    const handleClick = (event: MouseEvent) => {
        if (tag === 'button') {
            event.preventDefault();
        }
        onClick?.();
        onItemClick?.();
    };

    if (tag === 'link' && to) {
        return (
            <Link to={to} className={combinedClasses} onClick={handleClick}>
                {children}
            </Link>
        );
    }

    return (
        <button type="button" onClick={handleClick} className={combinedClasses}>
            {children}
        </button>
    );
};
