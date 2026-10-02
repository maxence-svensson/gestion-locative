<?php

declare(strict_types=1);

namespace App\Payment;

use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

final readonly class GeneratedReceipt
{
    public function __construct(
        public string $filename,
        public string $pdf,
    ) {
    }

    /**
     * « inline » : le navigateur affiche le PDF, avec un nom de fichier propre s'il est enregistré.
     */
    public function toResponse(): Response
    {
        return new Response($this->pdf, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, $this->filename),
        ]);
    }
}
