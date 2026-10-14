/** Raw shape returned by `GET /panel-superadmin`: las 6 cifras de plataforma del inicio del superadmin. */
export type ApiPanelSuperadmin = {
    logins_fallidos_7_dias: number;
    usuarios_activos: number;
    empresas_activas: number;
    servicios_caidos: number;
    ultimo_respaldo: {
        horas_desde_ultimo_diario: number | null;
        alerta: boolean;
    };
    errores_tareas_mes: number;
};
