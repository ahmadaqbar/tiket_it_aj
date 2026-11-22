<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Lampiran disimpan di disk "local" (storage/app/private) dengan nama acak.
 * Ekstensi ditentukan dari MIME hasil deteksi isi file, bukan dari nama file klien.
 */
class AttachmentService
{
    public const DISK = 'local';

    /** MIME yang diizinkan => ekstensi simpan. */
    public const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'text/plain' => 'txt',
    ];

    public const ALLOWED_EXTENSIONS = 'jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,txt';

    /** @var array<string> path yang ditulis selama request ini (untuk dibersihkan bila transaksi gagal) */
    private array $written = [];

    public function __construct(private readonly SettingService $settings) {}

    public function maxKilobytes(): int
    {
        return max(100, (int) $this->settings->get('upload.max_kb', 5120));
    }

    public function maxFiles(): int
    {
        return max(1, (int) $this->settings->get('upload.max_files', 5));
    }

    public function store(Ticket $ticket, UploadedFile $file, User $uploader, string $visibility = TicketAttachment::VISIBILITY_PUBLIC, ?int $replyId = null): TicketAttachment
    {
        $mime = (string) $file->getMimeType();
        $extension = self::ALLOWED[$mime] ?? 'bin';
        $path = $file->storeAs('attachments/'.now()->format('Y/m'), Str::ulid().'.'.$extension, self::DISK);
        $this->written[] = $path;

        return TicketAttachment::create([
            'ticket_id' => $ticket->id,
            'ticket_reply_id' => $replyId,
            'user_id' => $uploader->id,
            'original_name' => $this->safeName($file->getClientOriginalName(), $extension),
            'stored_path' => $path,
            'mime_type' => $mime,
            'size_bytes' => (int) $file->getSize(),
            'visibility' => $visibility,
        ]);
    }

    /** Hapus file yang sudah ditulis bila transaksi database gagal. */
    public function discardWritten(): void
    {
        foreach ($this->written as $path) {
            Storage::disk(self::DISK)->delete($path);
        }
        $this->written = [];
    }

    public function forgetWritten(): void
    {
        $this->written = [];
    }

    private function safeName(string $name, string $extension): string
    {
        $base = Str::of(pathinfo($name, PATHINFO_FILENAME))->ascii()->replaceMatches('/[^A-Za-z0-9._ -]/', '')->trim()->limit(120, '');

        return ($base->isEmpty() ? 'lampiran' : (string) $base).'.'.$extension;
    }
}
