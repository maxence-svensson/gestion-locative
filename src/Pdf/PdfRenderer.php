<?php

declare(strict_types=1);

namespace App\Pdf;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Convertit une page HTML en PDF A4 (Dompdf : en PHP pur, sans navigateur ni service externe).
 */
final class PdfRenderer
{
    public function render(string $html): string
    {
        $options = new Options();
        // Le document est entièrement généré par l'application : aucune ressource externe ni code PHP à exécuter
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        // DejaVu Sans contient les accents, le symbole € et les espaces insécables du format français
        $options->setDefaultFont('DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
