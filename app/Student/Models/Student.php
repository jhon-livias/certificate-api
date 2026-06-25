<?php

namespace App\Student\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    protected $fillable = [
        'student_code',
        'document_number',
        'full_name',
        'program',
        'modality',
        'faculty',
        'start_semester',
        'start_date',
        'academic_cycle',
        'current_semester',
        'graduation_semester',
        'graduation_date',
        'credits',
        'gender',
        'email',
        'phone',
        'address',
        'admission_mode',
        'status',
    ];

    protected $hidden = [
        'creator_user_id',
        'creation_time',
        'last_modification_time',
        'last_modifier_user_id',
        'is_deleted',
        'deleter_user_id',
        'deletion_time',
    ];

    public $timestamps = false;

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }
}
