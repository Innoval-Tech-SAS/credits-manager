const CODIGO_PAIS_COLOMBIA = '57';

/**
 * Número para wa.me en formato internacional, o null si el celular no sirve.
 * Colombia: móviles de 10 dígitos que empiezan por 3, con o sin el 57 delante.
 */
export function numeroWhatsApp(celular: string | null | undefined): string | null {
    if (!celular) {
        return null;
    }

    const digitos = celular.replace(/\D/g, '');

    if (digitos.length === 10 && digitos.startsWith('3')) {
        return CODIGO_PAIS_COLOMBIA + digitos;
    }

    if (digitos.length === 12 && digitos.startsWith(CODIGO_PAIS_COLOMBIA)) {
        return digitos;
    }

    return null;
}

/** Mensaje inicial. Quien envía lo edita antes de abrir WhatsApp. */
export function plantillaMensaje(nombre: string, empresa: string): string {
    const primerNombre = nombre.split(' ')[0];

    if (empresa === '') {
        return `Hola ${primerNombre}, le escribimos sobre su crédito. ¿Podemos conversar?`;
    }

    return `Hola ${primerNombre}, le escribe ${empresa} sobre su crédito. ¿Podemos conversar?`;
}

export function enlaceWhatsApp(numero: string, texto: string): string {
    return `https://wa.me/${numero}?text=${encodeURIComponent(texto)}`;
}
