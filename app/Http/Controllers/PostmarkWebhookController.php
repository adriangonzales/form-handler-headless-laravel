<?php

namespace App\Http\Controllers;

use App\Mail\NewFormEntry;
use App\Models\FormNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class PostmarkWebhookController extends Controller
{
    /**
     * Bounce types that do not mean the address failed to receive mail (out-of-office replies,
     * subscription changes, verification challenges), so they are not recorded as errors.
     *
     * @var list<string>
     */
    public const array INFORMATIONAL_BOUNCE_TYPES = ['AutoResponder', 'Subscribe', 'Unsubscribe', 'AddressChange', 'ChallengeVerification', 'OpenRelayTest'];

    /**
     * Record a bounce or spam complaint for an alert email on the recipient it was sent to, identified by
     * the recipient ID carried in the message metadata. Anything else, or a message this service did not
     * tag, is acknowledged and ignored so Postmark does not retry it.
     */
    public function __invoke(Request $request): Response
    {
        $recordType = $request->input('RecordType');
        $bounceType = (string) $request->input('Type', '');
        $recipientId = $request->input('Metadata.'.NewFormEntry::RECIPIENT_METADATA_KEY);

        $isFailure = $recordType === 'SpamComplaint'
            || ($recordType === 'Bounce' && ! in_array($bounceType, self::INFORMATIONAL_BOUNCE_TYPES, true));

        if ($isFailure && is_string($recipientId)) {
            FormNotification::withTrashed()->find($recipientId)?->update([
                'error' => $this->describe($recordType, $bounceType, $request->input('Description')),
            ]);
        }

        return response()->noContent();
    }

    private function describe(string $recordType, string $bounceType, mixed $description): string
    {
        $label = $recordType === 'SpamComplaint' ? 'Marked as spam' : 'Bounced ('.($bounceType ?: 'Unknown').')';
        $detail = is_string($description) && $description !== '' ? ': '.$description : '';

        return Str::limit($label.$detail, 255, '');
    }
}
