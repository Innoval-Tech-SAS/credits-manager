<?php

namespace App\Auditoria\Commands;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Repositories\BitacoraAuditoriaRepository;
use App\Auditoria\Services\RespaldoAuditoriaService;
use App\Redis\Contracts\RedisClienteInterface;
use Illuminate\Console\Command;

class EstadoAuditoriaCommand extends Command
{
    protected $signature = 'auditoria:estado';

    protected $description = 'Estado de la cola de auditoría (para detectar un listener caído) y del log de respaldo';

    public function handle(RedisClienteInterface $redis, RespaldoAuditoriaService $respaldo, BitacoraAuditoriaRepository $bitacora): int
    {
        $enStream = $redis->longitud(AuditoriaConstants::STREAM);
        $pendientes = $redis->pendientes(AuditoriaConstants::STREAM, AuditoriaConstants::GRUPO);

        $ids = array_values(array_filter(array_column($respaldo->entradas(), 'evento_id')));
        $sinRecuperar = count($ids) - count($bitacora->eventoIdsExistentes($ids));

        $this->table(['Métrica', 'Valor'], [
            ['Eventos en el stream (sin procesar)', $enStream],
            ['Entregados sin confirmar (pendientes)', $pendientes],
            ['En log de respaldo sin recuperar', $sinRecuperar],
        ]);

        if ($enStream - $pendientes > 0) {
            $this->warn('Hay eventos esperando: ¿el listener está caído?');
        }

        return self::SUCCESS;
    }
}
