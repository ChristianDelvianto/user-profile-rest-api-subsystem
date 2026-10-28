<?php

namespace App\Http\Resources\v1;

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
            'created_at' => $this->created_at,
            'last_activity_id' => $this->whenHas('last_activity_id'),
            'last_activity_at' => $this->whenHas('last_activity_at'),
            'users' => $this->whenLoaded('users', UserResource::collection($this->users))
        ];
    }
}
