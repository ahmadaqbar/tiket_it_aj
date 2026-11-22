<?php

namespace App\Http\Controllers;

use App\Models\TicketAttachment;
use App\Services\AttachmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Unduh lampiran melalui Policy — file tidak pernah berada di folder publik.
 */
class AttachmentController extends Controller
{
    public function show(Request $request, TicketAttachment $attachment): StreamedResponse
    {
        $attachment->load('ticket');
        abort_unless($attachment->ticket && $request->user()->can('download', $attachment), 404);

        $disk = Storage::disk(AttachmentService::DISK);
        abort_unless($disk->exists($attachment->stored_path), 404, 'File lampiran tidak ditemukan.');

        $headers = [
            'Content-Type' => $attachment->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=0, no-store',
        ];

        // Gambar ditampilkan inline (preview); jenis lain selalu diunduh.
        return $attachment->isImage() && $request->boolean('inline')
            ? $disk->response($attachment->stored_path, $attachment->original_name, $headers, 'inline')
            : $disk->download($attachment->stored_path, $attachment->original_name, $headers);
    }
}
