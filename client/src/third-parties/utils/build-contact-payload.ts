import type { ThirdPartyContactValues } from '../types/third-party-contact-values';

export function buildContactPayload(values: ThirdPartyContactValues) {
    return {
        direccion: values.direccion || null,
        barrio: values.barrio || null,
        ciudad: values.ciudad || null,
        celular: values.celular || null,
        telefono_fijo: values.telefonoFijo || null,
        correo: values.correo || null,
        regimen_tributario: values.regimenTributario || null,
        autorretenedor: values.autorretenedor,
        responsable_iva: values.responsableIva,
        actividad_economica_id: values.actividadEconomicaId ? Number(values.actividadEconomicaId) : null,
    };
}
