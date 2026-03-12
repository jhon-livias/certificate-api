<?php

namespace App\Auth\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class MeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $fullName = trim("{$this->name} {$this->surname}");
        return [
            'username' => $this->username,
            'email' => $this->email,
            'name' => $this->name,
            'surname' => $this->surname,
            'fullName' => $fullName ?: $this->username, // Fallback al username si no hay nombre
            'initials' => $this->getInitials($fullName ?: $this->username),
            'profilePicture' => $this->profile_picture,
            'role' => $this->role->name ?? 'User',
        ];
    }

    private function getInitials(?string $name): string
    {
        if (!$name)
            return '??';

        // Convertimos a mayúsculas al final de todo
        $initials = Str::of($name)
            ->words(2, '')
            ->explode(' ')
            ->map(fn($i) => mb_substr($i, 0, 1))
            ->join('');

        return Str::upper($initials);
    }
}
