<?php

namespace App\Services;

use App\Models\Documento;
use App\Models\Fiscalizacion;
use Illuminate\Support\Facades\Storage;

class ActaFiscalizacionPdfService
{
    public function generar(Fiscalizacion $fiscalizacion): array
    {
        $fiscalizacion->loadMissing([
            'establecimiento',
            'user',
            'precios.producto',
            'verificacion',
            'fiscalizacionIncumplimientos.incumplimientoCatalogo',
            'hechosVerificados',
            'observacion',
            'firmas',
        ]);

        $fiscalizacion->setRelation('precios', $fiscalizacion->precios
            ->sortBy(fn ($p) => $p->producto?->nombre ?? ('#' . $p->producto_id))->values());

        $expediente = trim((string) ($fiscalizacion->numero_expediente ?? ''));
        $slug = $expediente !== '' ? preg_replace('/[^A-Za-z0-9\-_]+/', '-', $expediente) : null;
        $filename = (($slug !== null && $slug !== '') ? 'acta-' . $slug : 'acta-fiscalizacion-' . $fiscalizacion->id) . '.pdf';
        $path = 'actas/' . $filename;

        $pdf = new MinimalPdf();
        $this->cabecera($pdf, $fiscalizacion, $expediente);
        $this->precios($pdf, $fiscalizacion);
        $this->hechos($pdf, $fiscalizacion);
        $this->otrosYFirmas($pdf, $fiscalizacion);

        $title = $expediente !== '' ? ('Acta ' . $expediente) : ('Acta Fiscalizacion ' . $fiscalizacion->id);
        $bytes = $pdf->render($title);
        $pages = $pdf->pageCount();

        $anterior = Documento::where('fiscalizacion_id', $fiscalizacion->id)->first();
        $oldPath = $anterior?->ruta_archivo;

        Storage::disk('public')->put($path, $bytes);

        if ($oldPath && $oldPath !== $path && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        $documento = Documento::updateOrCreate(
            ['fiscalizacion_id' => $fiscalizacion->id],
            [
                'nombre_archivo' => $filename,
                'ruta_archivo' => $path,
                'fecha_generacion' => now(),
                'numero_paginas' => $pages,
            ]
        );

        return ['bytes' => $bytes, 'documento' => $documento,
            'filename' => $filename, 'path' => $path, 'pages' => $pages];
    }

    private function cabecera(MinimalPdf $pdf, Fiscalizacion $f, string $expediente): void
    {
        $est = $f->establecimiento;
        $user = $f->user;
        $pdf->line('Organismo Supervisor de la Inversion en Energia y Mineria - OSINERGMIN', 9);
        $pdf->line('Oficina Regional Huanuco - Jr. 28 de Julio Nro. 1237, Huanuco', 9);
        $pdf->line('Telefono: (062) 512345', 9);
        $pdf->blank(6);
        $pdf->line('ACTA DE FISCALIZACION DEL CUMPLIMIENTO DEL PROCEDIMIENTO DE', 12);
        $pdf->line('ENTREGA DE INFORMACION DE PRECIOS DE COMBUSTIBLES', 12);
        $pdf->line('DERIVADOS DE HIDROCARBUROS - PRICE', 12);
        $pdf->blank(6);
        $pdf->line('Expediente Nro.: ' . ($expediente !== '' ? $expediente : '(sin numero de expediente)'), 11);
        $pdf->line('Fecha de diligencia: ' . $this->fecha($f->fecha_diligencia)
            . '   Hora apertura: ' . ($f->hora_apertura ?? '-')
            . '   Hora cierre: ' . ($f->hora_cierre ?? '-'), 10);
        $pdf->line('Para tramites posteriores senalar el numero de expediente.', 9);
        $pdf->blank(6);
        $pdf->line('I. INFORMACION RECABADA', 11);
        $pdf->line('Agente fiscalizado: ' . $this->v($f->agente_fiscalizado ?? $est?->razon_social), 10);
        $pdf->line('Codigo OSINERGMIN: ' . $this->v($f->codigo_osinergmin ?? $est?->codigo_osinergmin)
            . '   Registro Hidrocarburos: ' . $this->v($f->registro_hidrocarburos ?? $est?->registro_hidrocarburos), 10);
        $pdf->line('Direccion: ' . $this->v($f->direccion ?? $est?->direccion)
            . ' - ' . $this->v($f->distrito ?? $est?->distrito)
            . ' - ' . $this->v($f->provincia ?? $est?->provincia)
            . ' - ' . $this->v($f->departamento ?? $est?->departamento), 10);
        $pdf->line('RUC/DNI: ' . $this->v($f->ruc_dni ?? $est?->ruc_dni)
            . '   Telefono/Fax: ' . $this->v($f->telefono_fax ?? $est?->telefono), 10);
        $pdf->line('Fiscalizador responsable: ' . $this->v($user?->name)
            . ($user?->dni ? (' (DNI ' . $user->dni . ')') : ''), 10);
        $pdf->blank(6);
    }

    private function precios(MinimalPdf $pdf, Fiscalizacion $f): void
    {
        $ver = $f->verificacion;
        $pdf->line('Tabla de precios (por producto):', 10);
        $pdf->line('PRODUCTO | PRICE | PUBLICADO | SURTIDOR | DESCUENTO', 9);
        if ($f->precios->isEmpty()) {
            $pdf->line('(Sin precios registrados en esta fiscalizacion)', 10);
        }
        foreach ($f->precios as $precio) {
            $nombre = $precio->producto?->nombre ?? ('Producto #' . $precio->producto_id);
            $pdf->line($this->corta($nombre, 34) . ' | '
                . $this->monto($precio->precio_price) . ' | '
                . $this->monto($precio->precio_publicado) . ' | '
                . $this->monto($precio->precio_surtidor) . ' | '
                . ($precio->tiene_descuento ? $this->monto($precio->precio_descuento) : '-'), 9);
        }
        $pdf->blank(6);
        $pdf->line('Datos de verificacion:', 10);
        $pdf->line('Telefono publicado: ' . $this->v($ver?->telefono_publicado), 10);
        $pdf->line('Telefono registrado/actualizado en PRICE: ' . $this->v($ver?->telefono_actualizado_price), 10);
        $pdf->line('Horario publicado: ' . $this->v($ver?->horario_publicado), 10);
        $pdf->line('Observaciones de verificacion: ' . $this->v($ver?->observaciones), 10);
        $pdf->blank(6);
    }

    private function hechos(MinimalPdf $pdf, Fiscalizacion $f): void
    {
        $pdf->line('II. HECHOS VERIFICADOS', 11);
        $incs = $f->fiscalizacionIncumplimientos;
        $hechos = $f->hechosVerificados;
        if ($incs->isEmpty() && $hechos->isEmpty()) {
            $pdf->line('(Sin incumplimientos ni hechos verificados registrados)', 10);
        }
        $n = 1;
        foreach ($incs as $inc) {
            $cat = $inc->incumplimientoCatalogo;
            $titulo = ($cat?->codigo ? $cat->codigo . ' - ' : '') . ($cat?->descripcion ?? '(sin catalogo)');
            $pdf->line($n . '. INCUMPLIMIENTO: ' . $this->corta($titulo, 110), 10);
            $pdf->line('   Base legal: ' . $this->corta($this->v($cat?->base_legal), 100), 9);
            $pdf->line('   Seleccionado: ' . ($inc->seleccionado ? 'SI' : 'NO'), 9);
            if ($inc->observacion) {
                $pdf->line('   Observacion: ' . $this->corta($inc->observacion, 100), 9);
            }
            foreach ($hechos->where('fiscalizacion_incumplimiento_id', $inc->id)->values() as $h) {
                $pdf->line('   Hecho: ' . $this->corta($h->descripcion, 95) . ' (' . $this->fecha($h->fecha_registro) . ')', 9);
            }
            $n++;
        }
        foreach ($hechos->filter(fn ($h) => ! $incs->contains('id', $h->fiscalizacion_incumplimiento_id))->values() as $h) {
            $pdf->line($n . '. HECHO: ' . $this->corta($h->descripcion, 100) . ' (' . $this->fecha($h->fecha_registro) . ')', 10);
            $n++;
        }
        $pdf->blank(6);
    }

    private function otrosYFirmas(MinimalPdf $pdf, Fiscalizacion $f): void
    {
        $obs = $f->observacion;
        $pdf->line('III. OTROS', 11);
        $pdf->line('Otras ocurrencias: ' . $this->corta($this->v($obs?->otras_ocurrencias), 110), 10);
        $pdf->line('Documentacion recabada: ' . $this->corta($this->v($obs?->documentacion_recabada), 110), 10);
        $pdf->line('Manifestaciones del agente: ' . $this->corta($this->v($obs?->manifestaciones_agente), 110), 10);
        $pdf->line('Observaciones generales: ' . $this->corta($this->v($obs?->observaciones_generales), 110), 10);
        $pdf->line('Negativas [identificarse: ' . $this->sino($obs?->negativa_identificacion)
            . ' | suscribir: ' . $this->sino($obs?->negativa_suscripcion)
            . ' | recibir acta: ' . $this->sino($obs?->negativa_recepcion) . ']', 10);
        $pdf->blank(6);
        $user = $f->user;
        $fisc = $f->firmas->firstWhere('tipo_firma', 'FISCALIZADOR');
        $agente = $f->firmas->firstWhere('tipo_firma', 'AGENTE');
        $pdf->line('Firma del Fiscalizador de Osinergmin:', 10);
        $pdf->line('Apellidos y nombres: ' . $this->v($fisc?->nombre_completo ?? $user?->name), 10);
        $pdf->line('DNI: ' . $this->v($fisc?->dni ?? $user?->dni) . '   Fecha: ' . $this->fecha($fisc?->fecha_firma), 10);
        $pdf->blank(6);
        $pdf->line('Firma de quien recibe:', 10);
        $pdf->line('Apellidos y nombres: ' . $this->v($agente?->nombre_completo), 10);
        $pdf->line('DNI: ' . $this->v($agente?->dni) . '   Relacion: ' . $this->v($agente?->relacion_agente), 10);
        $pdf->line('Fecha: ' . $this->fecha($agente?->fecha_firma), 10);
        if ($f->firmas->count() > 2) {
            foreach ($f->firmas->skip(2) as $extra) {
                $pdf->line('Firma adicional [' . $extra->tipo_firma . ']: '
                    . $this->v($extra->nombre_completo) . ' DNI ' . $this->v($extra->dni), 9);
            }
        }
        $pdf->blank(6);
        $pdf->line('Base legal: procedimiento PRICE de entrega de informacion de precios', 9);
        $pdf->line('de combustibles derivados de hidrocarburos (segun catalogo de incumplimientos).', 9);
    }

    private function v(?string $value): string
    {
        $t = trim((string) ($value ?? ''));
        return $t !== '' ? $t : '-';
    }

    private function corta(?string $value, int $max): string
    {
        $t = trim((string) ($value ?? ''));
        if ($t === '') {
            return '-';
        }
        return mb_strlen($t) > $max ? mb_substr($t, 0, $max - 3) . '...' : $t;
    }

    private function monto(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }
        return number_format((float) $value, 2, '.', '');
    }

    private function fecha(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }
        try {
            if ($value instanceof \DateTimeInterface) {
                return $value->format('Y-m-d');
            }
            return substr((string) $value, 0, 10);
        } catch (\Throwable) {
            return '-';
        }
    }

    private function sino(mixed $value): string
    {
        return $value ? 'SI' : 'NO';
    }
}
