<?php

namespace App\Student\Models;

use Illuminate\Database\Eloquent\Model;

class IssuedCertificate extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'certificate_id',
        'student_code',
        'certificate_code',
        'file_path',
        'tracking_code',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'creator_user_id',
        'creation_time',
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

     /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'creation_time' => 'datetime',
            'last_modification_time' => 'datetime',
            'deletion_time' => 'datetime',
        ];
    }

    public function certificate()
    {
        return $this->belongsTo(Certificate::class, 'certificate_id');
    }
}
