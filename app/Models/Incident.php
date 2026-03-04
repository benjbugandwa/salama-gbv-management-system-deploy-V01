<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Incident extends Model
{
    use HasUuids;

    protected $table = 'incidents';

    // id est un uuid
    protected $keyType = 'string';
    public $incrementing = false;

    // Le script SQL n'a pas updated_at, seulement created_at
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'id',
        'survivant_id',
        'code_incident',
        'date_incident',
        'created_by',
        'severite',
        'statut_incident',
        'auteur_presume',
        'code_province',
        'code_territoire',
        'code_zonesante',
        'localite',
        'source_info',
        'description_faits',
        'created_at',
        'assigned_to',
        'assigned_by',
        'assigned_at',
        'last_status_changed_at',
        'confidentiality_level',
        'photo_url',
    ];

    protected $casts = [
        'date_incident' => 'datetime',
        'created_at' => 'datetime',
        'assigned_at' => 'datetime',
        'last_status_changed_at' => 'datetime',
    ];

    // Relations utiles (cohérentes avec les FK SQL)
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function province()
    {
        return $this->belongsTo(Province::class, 'code_province', 'code_province');
    }

    public function territoire()
    {
        return $this->belongsTo(Territoire::class, 'code_territoire', 'code_territoire');
    }

    public function zoneSante()
    {
        return $this->belongsTo(ZoneSante::class, 'code_zonesante', 'code_zonesante');
    }

    public function violences()
    {
        return $this->belongsToMany(Violence::class, 'violence_incidents', 'id_incident', 'id_violence')
            ->withPivot(['id', 'description_violence', 'created_by', 'created_at']);
    }

    public function violencesLinks()
    {
        return $this->hasMany(ViolenceIncident::class, 'id_incident', 'id');
    }



    public function caseNotes()
    {
        return $this->hasMany(\App\Models\CaseNote::class, 'id_incident', 'id');
    }

    public function assignedTo()
    {
        // adapte le nom de la colonne FK selon ta table incidents
        return $this->belongsTo(User::class, 'assigned_to', 'id');
    }

    public function referencements()
    {
        return $this->hasMany(Referencement::class, 'id_incident', 'id');
    }

    public function survivant()
    {
        // incidents.survivant_id (uuid) -> survivants.id (uuid)
        return $this->belongsTo(Survivant::class, 'survivant_id', 'id');
    }

    //public function violences() { return $this->belongsToMany(Violence::class, 'violence_incidents', 'id_incident', 'id_violence')->withPivot(['description_violence','created_by','created_at']); }
}
