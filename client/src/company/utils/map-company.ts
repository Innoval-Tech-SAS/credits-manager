import type { ApiCompany } from '../types/api-company';
import type { Company } from '../types/company';

export function mapCompany(apiCompany: ApiCompany): Company {
    return {
        id: apiCompany.id,
        name: apiCompany.nombre_empresa,
        taxId: apiCompany.nit,
        address: apiCompany.direccion,
        phone: apiCompany.telefono,
        email: apiCompany.correo,
        logoPath: apiCompany.logo_path,
    };
}
