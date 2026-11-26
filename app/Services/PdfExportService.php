<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;

class PdfExportService
{
    /**
     * Render Blade view menjadi PDF siap unduh (atau tampil inline jika $inline = true).
     *
     * @param  array<string, mixed>  $data
     */
    public function renderView(
        string $view,
        array $data,
        string $filename,
        string $paper = 'a4',
        string $orientation = 'landscape',
        bool $inline = false
    ): Response {
        $html = view($view, $data)->render();

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper($paper, $orientation);
        $dompdf->render();

        $safeName    = str_ends_with(strtolower($filename), '.pdf') ? $filename : ($filename . '.pdf');
        $disposition = $inline ? 'inline' : 'attachment';

        return response($dompdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "{$disposition}; filename=\"{$safeName}\"",
            'Cache-Control'       => 'max-age=0, no-cache, no-store, must-revalidate',
        ]);
    }
}