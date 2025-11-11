<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubjectSession extends Model
{
    use HasFactory;

    // Tenant DB connection
    protected $connection = 'tenant';

    protected $fillable = [
        'session_id',
        'subject_id',
        'order_index',
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