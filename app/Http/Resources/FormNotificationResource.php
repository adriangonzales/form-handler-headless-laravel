<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\FormNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FormNotification
 */
class FormNotificationResource extends JsonResource
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
            'type' => $this->type,
            'value' => $this->value,
            'enabled' => $this->enabled,
            'error' => $this->error,
        ];
    }
}
