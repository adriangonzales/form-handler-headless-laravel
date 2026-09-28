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
            'data' => $this->data,
            'ip' => $this->ip,
            'ip_location_display' => $this->ip_location_display,
            'referer' => $this->referer,
            'user_agent' => $this->user_agent,
            'user_agent_display' => $this->user_agent_display,
            'spam' => $this->spam,
            'spam_score' => $this->spam_score,
            'spam_reason' => $this->spam_reason,
            'starred' => $this->starred,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
