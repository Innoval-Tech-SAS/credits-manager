import type { FC } from 'react';

import type { ApiEconomicActivity } from '../../expenses/types/api-economic-activity';
import { useResource } from '../../shared/hooks/use-resource';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { Select } from '@ui/forms/components/select';
import { ToggleSwitchInput } from '@ui/forms/components/toggle-switch-input';
import type { ThirdPartyContactFieldsProps } from '../props/third-party-contact-fields-props';

export const ThirdPartyContactFields: FC<ThirdPartyContactFieldsProps> = ({ values, onChange }) => {
    const { data: actividadesData } = useResource<{ actividades_economicas: ApiEconomicActivity[] }>('/gastos/actividades-economicas');
    const actividadOptions = (actividadesData?.actividades_economicas ?? []).map((actividad) => ({
        value: String(actividad.id),
        label: `${actividad.codigo} - ${actividad.nombre}`,
    }));

    return (
        <>
            <div>
                <p className="mb-3 text-xs font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">Ubicación</p>
                <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div>
                        <Label htmlFor="direccion">Dirección</Label>
                        <Input id="direccion" name="direccion" value={values.direccion} onChange={(event) => onChange('direccion', event.target.value)} />
                    </div>

                    <div>
                        <Label htmlFor="barrio">Barrio</Label>
                        <Input id="barrio" name="barrio" value={values.barrio} onChange={(event) => onChange('barrio', event.target.value)} />
                    </div>

                    <div>
                        <Label htmlFor="ciudad">Ciudad</Label>
                        <Input id="ciudad" name="ciudad" value={values.ciudad} onChange={(event) => onChange('ciudad', event.target.value)} />
                    </div>
                </div>
            </div>

            <div>
                <p className="mb-3 text-xs font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">Contacto</p>
                <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div>
                        <Label htmlFor="celular">Celular</Label>
                        <Input id="celular" name="celular" value={values.celular} onChange={(event) => onChange('celular', event.target.value)} />
                    </div>

                    <div>
                        <Label htmlFor="telefono-fijo">Teléfono fijo</Label>
                        <Input
                            id="telefono-fijo"
                            name="telefono-fijo"
                            value={values.telefonoFijo}
                            onChange={(event) => onChange('telefonoFijo', event.target.value)}
                        />
                    </div>

                    <div>
                        <Label htmlFor="correo">Correo</Label>
                        <Input
                            id="correo"
                            name="correo"
                            type="email"
                            value={values.correo}
                            onChange={(event) => onChange('correo', event.target.value)}
                        />
                    </div>
                </div>
            </div>

            <div>
                <p className="mb-3 text-xs font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">Datos fiscales</p>
                <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div>
                        <Label htmlFor="regimen-tributario">Régimen tributario</Label>
                        <Input
                            id="regimen-tributario"
                            name="regimen-tributario"
                            value={values.regimenTributario}
                            onChange={(event) => onChange('regimenTributario', event.target.value)}
                        />
                    </div>

                    <div>
                        <Label htmlFor="actividad-economica">Actividad económica</Label>
                        <Select
                            options={actividadOptions}
                            placeholder="--"
                            defaultValue={values.actividadEconomicaId}
                            onChange={(value) => onChange('actividadEconomicaId', value)}
                        />
                    </div>
                </div>

                <div className="mt-4 flex flex-wrap gap-8">
                    <ToggleSwitchInput
                        id="autorretenedor"
                        label="Autorretenedor"
                        checked={values.autorretenedor}
                        onChange={(checked) => onChange('autorretenedor', checked)}
                    />
                    <ToggleSwitchInput
                        id="responsable-iva"
                        label="Responsable de IVA"
                        checked={values.responsableIva}
                        onChange={(checked) => onChange('responsableIva', checked)}
                    />
                </div>
            </div>
        </>
    );
};
