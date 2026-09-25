const WEIGHTS = [3, 7, 13, 17, 19, 23, 29, 37, 41, 43, 47, 53, 59, 67, 71];

/** Dígito de verificación del NIT (módulo 11 de la DIAN); vacío mientras no haya dígitos. */
export function calculateVerificationDigit(nit: string): string {
    const digits = nit.replace(/\D/g, '');

    if (!digits) {
        return '';
    }

    const sum = [...digits].reverse().reduce((total, digit, position) => total + Number(digit) * (WEIGHTS[position] ?? 0), 0);
    const remainder = sum % 11;

    return String(remainder > 1 ? 11 - remainder : remainder);
}
