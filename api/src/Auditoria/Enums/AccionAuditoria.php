<?php

namespace App\Auditoria\Enums;

enum AccionAuditoria: string
{
    case Crear = 'crear';
    case Modificar = 'modificar';
    case Eliminar = 'eliminar';
    case Aprobar = 'aprobar';
    case Desembolsar = 'desembolsar';
    case Cancelar = 'cancelar';
    case Anular = 'anular';
    case Revertir = 'revertir';
    case Login = 'login';
    case Logout = 'logout';
    case CambioPermisos = 'cambio_permisos';
    case Importar = 'importar';
    case Exportar = 'exportar';
    case Recuperar = 'recuperar';
    case Causar = 'causar';
    case Contabilizar = 'contabilizar';
    case Reversar = 'reversar';
    case CerrarPeriodo = 'cerrar_periodo';
    case ReabrirPeriodo = 'reabrir_periodo';
}
