export type AdvisorSelectProps = {
    /** Id del asesor elegido (`null` si aún no hay selección). */
    value: number | null;
    onChange: (advisorId: number | null) => void;
    /** Asesor que no se ofrece como opción (p. ej. el actual al reasignar). */
    excludeId?: number;
};
