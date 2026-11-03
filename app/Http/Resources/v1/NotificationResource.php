<?php

namespace App\Http\Resources\v1;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'type' =>  $this->type,
            'groups_count' => $this->groups_count,
            'created_at' => $this->created_at->getTimestamp(),
            'last_activity_id' => $this->whenHas('last_activity_id'),
            'last_activity_at' => $this->whenHas('last_activity_at', $this->last_activity_at->getTimestamp()),
            'is_read' => $this->whenHas('is_read'),
            'users' => $this->whenLoaded('users', UserResource::collection($this->users))
        ];
    }
}
