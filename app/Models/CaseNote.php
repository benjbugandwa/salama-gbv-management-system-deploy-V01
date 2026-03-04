<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaseNote extends Model
{
    protected $table = 'case_notes';

    protected $fillable = [
        'id_incident',
        'case_note',
        'is_confidential',
        'file_path',
        'created_by',
    ];

    protected $casts = [
        'is_confidential' => 'boolean',
    ];

    public function incident()
    {
        return $this->belongsTo(Incident::class, 'id_incident', 'id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }
}
