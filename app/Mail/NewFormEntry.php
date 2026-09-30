<?php

declare(strict_types=1);

namespace App\Mail;

use App\Actions\Forms\MapFormData;
use App\Models\FormEntry;
use App\Models\FormNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewFormEntry extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * Metadata key identifying the recipient, returned by Postmark in bounce webhooks.
     */
    public const string RECIPIENT_METADATA_KEY = 'form_notification_id';

    /**
     * Create a new message instance.
     */
    public function __construct(public FormEntry $formEntry, public ?FormNotification $recipient = null) {}

    /**
     * Get the message envelope. The recipient's ID is attached as metadata so a later bounce can be
     * recorded against it.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New entry: '.$this->formEntry->form->name,
            metadata: $this->recipient instanceof FormNotification ? [self::RECIPIENT_METADATA_KEY => $this->recipient->id] : [],
        );
    }

    /**
     * Get the message content definition. Plain Blade views (not Markdown) so submitted values are
     * escaped and can never render as links or formatting.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.new-form-entry',
            text: 'emails.new-form-entry-text',
            with: [
                'formName' => $this->formEntry->form->name,
                'submittedAt' => $this->formEntry->created_at?->toDayDateTimeString().' UTC',
                'fields' => collect((new MapFormData)($this->formEntry))
                    ->map(fn (array $field): array => [
                        'label' => $field['label'],
                        'value' => $this->displayValue($field['data']),
                    ])
                    ->values()
                    ->all(),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    private function displayValue(mixed $value): string
    {
        return match (true) {
            $value === null, $value === '' => '—',
            is_bool($value) => $value ? 'Yes' : 'No',
            is_array($value) => implode(', ', array_map(fn (mixed $item): string => is_scalar($item) ? (string) $item : (string) json_encode($item), $value)),
            default => (string) $value,
        };
    }
}
