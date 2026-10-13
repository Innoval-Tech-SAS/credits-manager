export type BackupClass = 'diario' | 'semanal' | 'mensual' | 'manual';

export type ApiBackupEntry = {
    carpeta: string;
    creado_en: string;
    tamano_bytes: number;
    completo: boolean;
};

/** Raw shape returned by `GET /monitoreo/respaldos`. */
export type ApiBackupsStatus = {
    ultimo_diario: ApiBackupEntry | null;
    horas_desde_ultimo_diario: number | null;
    alerta: boolean;
    hora_automatica: string;
    externo: {
        configurado: boolean;
        ultimo_resultado: { respaldo: string; exitoso: boolean; detalle: string | null; registrado_en: string } | null;
    };
    clases: { clase: BackupClass; retencion_dias: number; respaldos: ApiBackupEntry[] }[];
};
