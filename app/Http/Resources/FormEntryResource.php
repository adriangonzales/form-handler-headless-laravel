<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\FormEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FormEntry
 */
class FormEntryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'form_id' => $this->form_id,
            'input' => $this->input,
            'ip' => $this->ip,
            'ip_location_display' => $this->ip_location_display,
            'referer' => $this->referer,
            'user_agent' => $this->user_agent,
            'user_agent_display' => $this->user_agent_display,
            'spam' => $this->spam,
            /** Spam likelihood from 0 (not spam) to 1 (spam), to two decimal places. */
            'spam_score' => $this->spam_score,
            'spam_reason' => $this->spam_reason,
            /**
             * When the entry's spam check finished: when it was created for honeypot hits and entries
             * created through the API, or when Jev classified a public submission. `null` while a
             * submission awaits classification, or if classification was unavailable.
             */
            'spam_checked_at' => $this->spam_checked_at,
            'starred' => $this->starred,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
