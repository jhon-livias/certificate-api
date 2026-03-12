<?php

namespace App\Student\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'student_code',
        'document_number',
        'full_name',
        'gender',
        'email',
        'phone',
        'address',
        'admission_mode',
        'program',
        'campus',
        'modality',
        'shift',
        'status',
        'graduation_year'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'creator_user_id',
        'last_modification_time',
        'last_modifier_user_id',
        'is_deleted',
        'deleter_user_id',
        'deletion_time',
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    // Accessor para obtener el trato (Sr. o Srta.) dinámicamente
    protected function titlePrefix(): Attribute
    {
        return Attribute::make(
            get: fn () => strtoupper($this->gender) === 'F' ? 'Srta.' : 'Sr.',
        );
    }

    // Accessor para obtener el artículo (el o la) dinámicamente
    protected function articlePrefix(): Attribute
    {
        return Attribute::make(
            get: fn () => strtoupper($this->gender) === 'F' ? 'la' : 'el',
        );
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }
}
