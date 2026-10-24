export type TaxObligationTypesModalProps = {
    isOpen: boolean;
    onClose: () => void;
    /** El calendario mantiene su propia lista de tipos activos para el selector del evento; se refresca con esto. */
    onChanged: () => void;
};
