<?php

namespace Tests\Feature\Clientes;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesCreditFixtures;
use Tests\TestCase;

class ClienteDocumentoTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesCreditFixtures;
    use RefreshDatabase;

    public function test_sube_un_documento_valido(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();

        $archivo = UploadedFile::fake()->create('cedula.pdf', 200, 'application/pdf');

        $respuesta = $this->postJson("/api/v1/clientes/{$cliente->id}/documentos", ['archivo' => $archivo]);

        $respuesta->assertStatus(201);
        $this->assertDatabaseHas('archivos_adjuntos', [
            'adjuntable_type' => $cliente->getMorphClass(),
            'adjuntable_id' => $cliente->id,
            'nombre_original' => 'cedula.pdf',
        ]);
    }

    public function test_rechaza_tipo_de_archivo_no_permitido(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();

        $archivo = UploadedFile::fake()->create('virus.exe', 100, 'application/octet-stream');

        $respuesta = $this->postJson("/api/v1/clientes/{$cliente->id}/documentos", ['archivo' => $archivo]);

        $respuesta->assertStatus(422)->assertJsonValidationErrors('archivo');
        $this->assertDatabaseCount('archivos_adjuntos', 0);
    }

    public function test_rechaza_archivo_que_excede_el_tamano_maximo(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();

        $archivo = UploadedFile::fake()->create('grande.pdf', 6000, 'application/pdf');

        $respuesta = $this->postJson("/api/v1/clientes/{$cliente->id}/documentos", ['archivo' => $archivo]);

        $respuesta->assertStatus(422)->assertJsonValidationErrors('archivo');
    }

    public function test_lista_documentos_del_cliente(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();

        $this->postJson("/api/v1/clientes/{$cliente->id}/documentos", [
            'archivo' => UploadedFile::fake()->create('cedula.pdf', 200, 'application/pdf'),
        ])->assertStatus(201);

        $respuesta = $this->getJson("/api/v1/clientes/{$cliente->id}/documentos");

        $respuesta->assertStatus(200);
        $this->assertCount(1, $respuesta->json('documentos'));
    }

    public function test_sin_autenticar_no_puede_subir_documentos(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();
        $cliente = $this->crearCliente();
        $this->salirDeSesion();

        $archivo = UploadedFile::fake()->create('cedula.pdf', 200, 'application/pdf');

        $respuesta = $this->postJson("/api/v1/clientes/{$cliente->id}/documentos", ['archivo' => $archivo]);

        $respuesta->assertStatus(401);
    }

    public function test_asesor_sin_permiso_no_puede_subir_documentos(): void
    {
        Storage::fake('public');
        $this->actingAsAsesor();
        $cliente = $this->crearCliente();

        $archivo = UploadedFile::fake()->create('cedula.pdf', 200, 'application/pdf');

        $respuesta = $this->postJson("/api/v1/clientes/{$cliente->id}/documentos", ['archivo' => $archivo]);

        $respuesta->assertStatus(403);
    }
}
