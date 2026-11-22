<?php

namespace App\Services;

use App\Enums\ReplyVisibility;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketHistory;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class TicketReplyService
{
    public function __construct(
        private readonly TicketService $tickets,
        private readonly AttachmentService $attachments,
        private readonly AuditService $audit,
    ) {}

    /**
     * @param  array<UploadedFile>  $files
     */
    public function add(Ticket $ticket, User $author, string $message, ReplyVisibility $visibility, array $files = [], bool $recordHistory = true): TicketReply
    {
        // Aturan visibilitas ditegakkan di service, bukan hanya di form.
        if ($visibility === ReplyVisibility::INTERNAL && ! $author->isItStaff()) {
            throw ValidationException::withMessages(['visibility' => 'Catatan internal hanya untuk IT.']);
        }
        if (! $author->isItStaff() && $ticket->user_id !== $author->id) {
            throw ValidationException::withMessages(['message' => 'Anda tidak dapat membalas tiket ini.']);
        }
        if ($ticket->isClosed() && $visibility === ReplyVisibility::PUBLIC) {
            throw ValidationException::withMessages(['message' => 'Tiket sudah ditutup dan tidak dapat dibalas.']);
        }

        try {
            $reply = DB::transaction(function () use ($ticket, $author, $message, $visibility, $files, $recordHistory) {
                $reply = TicketReply::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $author->id,
                    'message' => $message,
                    'visibility' => $visibility,
                ]);

                $fileVisibility = $visibility === ReplyVisibility::INTERNAL
                    ? TicketAttachment::VISIBILITY_INTERNAL : TicketAttachment::VISIBILITY_PUBLIC;
                foreach ($files as $file) {
                    $this->attachments->store($ticket, $file, $author, $fileVisibility, $reply->id);
                }

                // Respons pertama IT yang terlihat pelapor menjadi dasar metrik response time.
                if ($author->isItStaff() && $visibility === ReplyVisibility::PUBLIC && $ticket->first_response_at === null) {
                    $ticket->forceFill(['first_response_at' => now()])->save();
                }
                $ticket->touch();

                if ($recordHistory) {
                    $internal = $visibility === ReplyVisibility::INTERNAL;
                    $this->tickets->history($ticket, $author, $internal ? TicketHistory::INTERNAL_NOTE : TicketHistory::REPLIED,
                        note: $files !== [] ? count($files).' lampiran' : null);
                }

                $this->audit->log($visibility === ReplyVisibility::INTERNAL ? 'ticket.internal_note' : 'ticket.replied', $ticket,
                    "{$ticket->ticket_number}: ".Str::limit(strip_tags($message), 60), newValues: ['reply_id' => $reply->id, 'attachments' => count($files)]);

                return $reply;
            });
        } catch (Throwable $e) {
            $this->attachments->discardWritten();
            throw $e;
        }

        $this->attachments->forgetWritten();

        return $reply;
    }
}
