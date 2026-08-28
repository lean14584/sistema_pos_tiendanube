<?php

namespace App\Mail;

use App\Models\Client;
use App\Models\CompanySettings;
use App\Support\AccountStatementPdfBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email al cliente con el resumen de su cuenta corriente en PDF adjunto.
 */
class ClientAccountStatementMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Client $client, public float $saldo) {}

    public function envelope(): Envelope
    {
        $empresa = CompanySettings::current()->nombre_fantasia ?: config('app.name');

        return new Envelope(
            subject: "Resumen de cuenta - {$empresa}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.cuenta-corriente',
            with: [
                'nombre' => $this->client->name,
                'saldo' => $this->saldo,
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
                fn () => app(AccountStatementPdfBuilder::class)->binaryForClient($this->client),
                "cuenta-corriente-{$this->client->name}.pdf",
            )->withMime('application/pdf'),
        ];
    }
}
