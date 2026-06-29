<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status?->value ?? $this->status,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')->values()),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
