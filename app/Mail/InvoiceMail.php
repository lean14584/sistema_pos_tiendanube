<?php

namespace App\Mail;

use App\Models\CompanySettings;
use App\Models\Invoice;
use App\Support\InvoicePdfBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email al cliente con la factura en PDF adjunta.
 */
class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Invoice $invoice) {}

    public function envelope(): Envelope
    {
        $empresa = CompanySettings::current()->nombre_fantasia ?: config('app.name');

        return new Envelope(
            subject: "Factura {$this->invoice->number} - {$empresa}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.invoice',
            with: [
                'invoice' => $this->invoice,
                'empresa' => CompanySettings::current(),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn () => app(InvoicePdfBuilder::class)->binary($this->invoice),
                "{$this->invoice->number}.pdf",
            )->withMime('application/pdf'),
        ];
    }
}
