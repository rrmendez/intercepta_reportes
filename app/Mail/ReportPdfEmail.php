<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Client;
use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReportPdfEmail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Report $report,
        public Client $client,
        public string $pdfBinary,
        public string $attachmentFilename,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reporte: '.$this->client->name.' · '.$this->report->date_from?->format('d/m/Y').' - '.$this->report->date_until?->format('d/m/Y'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.report-pdf',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => $this->pdfBinary, $this->attachmentFilename)
                ->withMime('application/pdf'),
        ];
    }
}
