<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Branch;
use App\Models\Media;
use Barryvdh\DomPDF\PDF;
use Illuminate\Http\Response;

class LetterheadPdf
{
    /**
     * @param  array{letter?: array<string, string|null>, slip?: array<string, mixed>|null}  $document
     */
    public function stream(Branch $branch, array $document, string $filename): Response
    {
        $pdf = app(PDF::class);
        $pdf->loadView('letterhead.document', [
            'branchName' => $branch->displayName(app()->getLocale()),
            'address' => trim($branch->address.($branch->city ? ', '.$branch->city : '')),
            'phone' => $branch->phone,
            'logo' => $this->logoData($branch),
            'letter' => $document['letter'] ?? null,
            'slip' => $document['slip'] ?? null,
            'printedAt' => now()->format('d M Y'),
        ]);

        return $pdf->stream($filename);
    }

    private function logoData(Branch $branch): ?string
    {
        $image = $branch->media()
            ->where('kind', Media::KIND_IMAGE)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        if ($image === null || $image->path === null) {
            return null;
        }

        $full = storage_path('app/public/'.ltrim($image->path, '/'));

        if (! is_file($full)) {
            return null;
        }

        $mime = mime_content_type($full) ?: 'image/jpeg';

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($full));
    }
}
