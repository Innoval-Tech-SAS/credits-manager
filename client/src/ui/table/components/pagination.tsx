import type { FC } from 'react';

import { ChevronLeft, ChevronRight } from 'lucide-react';
import type { PaginationProps } from '../props/pagination-props';
import { buildPageWindow } from '../utils/build-page-window';

export const Pagination: FC<PaginationProps> = ({ page, totalPages, onPageChange }) => {
    if (totalPages <= 1) {
        return null;
    }

    const pageWindow = buildPageWindow(page, totalPages);

    return (
        <div className="flex items-center justify-end gap-1">
            <button
                type="button"
                disabled={page === 1}
                onClick={() => onPageChange(page - 1)}
                className="flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-800 dark:text-gray-400 dark:hover:bg-white/5"
            >
                <ChevronLeft className="size-4" />
            </button>

            {pageWindow[0] > 1 && <span className="flex h-9 w-9 items-center justify-center text-sm text-gray-400">…</span>}

            {pageWindow.map((pageNumber) => (
                <button
                    key={pageNumber}
                    type="button"
                    onClick={() => onPageChange(pageNumber)}
                    className={`flex h-9 w-9 items-center justify-center rounded-lg text-sm font-medium ${
                        pageNumber === page
                            ? 'bg-brand-500 text-white'
                            : 'text-gray-700 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-white/5'
                    }`}
                >
                    {pageNumber}
                </button>
            ))}

            {pageWindow[pageWindow.length - 1] < totalPages && (
                <span className="flex h-9 w-9 items-center justify-center text-sm text-gray-400">…</span>
            )}

            <button
                type="button"
                disabled={page === totalPages}
                onClick={() => onPageChange(page + 1)}
                className="flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-800 dark:text-gray-400 dark:hover:bg-white/5"
            >
                <ChevronRight className="size-4" />
            </button>
        </div>
    );
};
