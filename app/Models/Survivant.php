<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Survivant extends Model
{
    use HasUuids;

    protected $table = 'survivants';

    protected $keyType = 'string';
    public $incrementing = false;

    // Le SQL ne définit pas created_at/updated_at pour survivants
    public $timestamps = false;

    protected $fillable = [
        'id',
        'code_survivant',      // :contentReference[oaicite:2]{index=2}
        'full_name',           // :contentReference[oaicite:3]{index=3}
        'age_survivant',       // :contentReference[oaicite:4]{index=4}
        'sexe_survivant',      // :contentReference[oaicite:5]{index=5}
        'marital_status',      // :contentReference[oaicite:6]{index=6}
        'disability_status',   // :contentReference[oaicite:7]{index=7}
        'observations',        // :contentReference[oaicite:8]{index=8}

        // Ajouts
        'adresses',
        'est_mineure',
        'tuteur_nom',
        'tuteur_numero',
        'created_by',
    ];

    protected $casts = [
        'disability_status' => 'boolean',
        'est_mineure' => 'boolean',
        'age_survivant' => 'integer',
    ];

    public function incidents()
    {
        return $this->hasMany(Incident::class, 'survivant_id', 'id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }
}
