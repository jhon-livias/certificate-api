<?php

namespace App\Student\Resources;

use App\Student\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Student */
class StudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'studentCode' => $this->student_code,
            'documentNumber' => $this->document_number,
            'fullName' => $this->full_name,
            'program' => $this->program,
            'modality' => $this->modality,
            'faculty' => $this->faculty,
            'cycle' => $this->academic_cycle,
            'currentSemester' => $this->current_semester,
            'email' => $this->email,
            'status' => $this->status,
        ];
    }
}
