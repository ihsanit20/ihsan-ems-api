<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SessionSubject extends Model
{
    use HasFactory;

    protected $connection = 'tenant';
    protected $table = 'session_subjects';

    protected $fillable = [
        'session_id',
        'subject_id',
        'sort_order',
        'book_name',
    ];

    /** Relations */
    public function academicSession()
    {
        return $this->belongsTo(AcademicSession::class, 'session_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }
}
