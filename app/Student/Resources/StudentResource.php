<?php

namespace App\Student\Resources;

use App\Student\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** * @mixin Student
 * @property int $id
 * @property string $student_code
 * @property string $document_number
 * @property string $full_name
 * @property string $gender
 * @property string $email
 * @property string $phone
 * @property string $address
 * @property string $admission_mode
 * @property string $program
 * @property string $campus
 * @property string $modality
 * @property string $shift
 * @property string $status
 * @property int $graduation_year
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
            'studentCode' => $this->student_code,
            'documentNumber' => $this->document_number,
            'fullName' => $this->full_name,
            'gender' => $this->gender,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'admissionMode' => $this->admission_mode,
            'program' => $this->program,
            'campus' => $this->campus,
            'modality' => $this->modality,
            'shift' => $this->shift,
            'status' => $this->status,
            'graduationYear' => $this->graduation_year,
        ];
    }
}
