import { describe, expect, it } from 'vitest';

import { formatImportDate } from './format-import-date';
import { importStepIndex } from './import-step-index';
import { IMPORT_STATUS_BADGE_CLASSES, IMPORT_STATUS_LABELS, IMPORT_STATUSES, IMPORT_TRANSITIONAL_STATUSES } from '../constants/import-status';
import { IMPORT_TYPE_LABELS, IMPORT_TYPE_OPTIONS, IMPORT_TYPES, IMPORT_TYPES_WITH_ACCOUNTING } from '../constants/import-types';

describe('formatImportDate', () => {
    it('muestra la fecha y la hora sin zona, sin segundos', () => {
        expect(formatImportDate('2026-10-05 07:07:42')).toBe('2026-10-05 07:07');
        expect(formatImportDate('2026-10-05T07:07:42.000000Z')).toBe('2026-10-05 07:07');
    });

    it('muestra un guion cuando no hay fecha', () => {
        expect(formatImportDate(null)).toBe('—');
    });
});

describe('importStepIndex', () => {
    it.each([
        ['pendiente', 2],
        ['validando', 2],
        ['pendiente_decision', 3],
        ['importando', 4],
        ['completada', 5],
        ['fallida', 5],
        ['cancelada', 5],
    ] as const)('el estado %s activa el paso %i', (estado, paso) => {
        expect(importStepIndex(estado)).toBe(paso);
    });
});

describe('constantes del asistente', () => {
    it('cada estado tiene etiqueta y clases de insignia', () => {
        for (const estado of IMPORT_STATUSES) {
            expect(IMPORT_STATUS_LABELS[estado]).toBeTruthy();
            expect(IMPORT_STATUS_BADGE_CLASSES[estado]).toContain('dark:');
        }
    });

    it('los estados en proceso son exactamente los que la pantalla vigila', () => {
        expect([...IMPORT_TRANSITIONAL_STATUSES].sort()).toEqual(['importando', 'pendiente', 'validando']);
    });

    it('cada hoja tiene opción en el selector y etiqueta, en el orden de carga', () => {
        expect(IMPORT_TYPE_OPTIONS.map((option) => option.value)).toEqual(expect.arrayContaining([...IMPORT_TYPES]));
        expect(IMPORT_TYPE_OPTIONS).toHaveLength(IMPORT_TYPES.length);
        for (const tipo of IMPORT_TYPES) {
            expect(IMPORT_TYPE_LABELS[tipo]).toBeTruthy();
        }
        expect(IMPORT_TYPE_OPTIONS[0].value).toBe('clientes');
    });

    it('solo créditos y pagos contabilizan', () => {
        expect(IMPORT_TYPES_WITH_ACCOUNTING).toEqual(['creditos', 'pagos']);
    });
});
