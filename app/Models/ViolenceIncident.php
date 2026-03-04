<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ViolenceIncident extends Model
{
    protected $table = 'violence_incidents';
    public $timestamps = false; // ton script a seulement created_at

    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'id_incident',
        'id_violence',
        'description_violence',
        'created_by',
        'created_at',
    ];

    public function incident()
    {
        return $this->belongsTo(Incident::class, 'id_incident', 'id');
    }

    public function violence()
    {
        return $this->belongsTo(Violence::class, 'id_violence', 'id');
    }
}
