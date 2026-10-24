import { PAGES_TO_SHOW } from '../constants/pagination';

export function buildPageWindow(page: number, totalPages: number): number[] {
    const start = Math.max(1, page - Math.floor(PAGES_TO_SHOW / 2));
    const end = Math.min(totalPages, start + PAGES_TO_SHOW - 1);

    return Array.from({ length: end - start + 1 }, (_, index) => start + index);
}
