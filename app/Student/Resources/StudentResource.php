<?php

namespace App\Student\Resources;

use App\Student\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** * @mixin Student
 * @property int $id
 * @property string $name
 * @property string $surname
 * @property string $dni
 * @property string $program_type
 * @property string $program
 * @property string $period
 * @property string $email
 * @property string $status
 */
class StudentResource extends JsonResource
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
            'name' => $this->name,
            'surname' => $this->surname,
            'dni' => $this->dni,
            'programType' => $this->program_type,
            'program' => $this->program,
            'email' => $this->email,
            'status' => $this->status,
        ];
    }
}
