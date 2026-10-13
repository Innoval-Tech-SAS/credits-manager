import { describe, expect, it } from 'vitest';

import { formatBytes } from './format-bytes';
import { formatUptime } from './format-uptime';

describe('formatBytes', () => {
    it('usa la unidad más grande con una cifra decimal', () => {
        expect(formatBytes(512)).toBe('512 B');
        expect(formatBytes(1536)).toBe('1,5 KB');
        expect(formatBytes(8000000 * 1024)).toBe('7,6 GB');
    });

    it('muestra una raya sin lectura', () => {
        expect(formatBytes(null)).toBe('—');
    });
});

describe('formatUptime', () => {
    it('resume en días, horas o minutos según el tamaño', () => {
        expect(formatUptime(93_784)).toBe('1 d 2 h');
        expect(formatUptime(3 * 3_600 + 20 * 60)).toBe('3 h 20 min');
        expect(formatUptime(45 * 60)).toBe('45 min');
        expect(formatUptime(null)).toBe('—');
    });
});
