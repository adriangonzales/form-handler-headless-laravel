<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Form;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Form
 */
class FormResource extends JsonResource
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
            'user_id' => $this->user_id,
            'name' => $this->name,
            'active' => $this->active,
            'schema' => $this->schema,
            /** @var array{redirect: string|null, timezone: string|null, domains: list<string>|null, message: string|null, honeypot_enabled: bool, honeypot_name: string|null}|null */
            'settings' => $this->settings,
            /** Entries on the form, excluding deleted ones. Included in the form list only. */
            'entries_count' => $this->whenCounted('entries'),
            /** Entries on the form not yet marked read, excluding deleted ones. Included in the form list only. */
            'unread_entries_count' => $this->whenCounted('unread_entries'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
