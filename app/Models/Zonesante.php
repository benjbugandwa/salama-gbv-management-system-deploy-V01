<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Zonesante extends Model
{
    protected $table = 'zonesantes';
    protected $primaryKey = 'code_zonesante';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['code_zonesante
    ', 'nom_zonesante', 'code_territoire'];

    public function territoire()
    {
        return $this->belongsTo(Territoire::class, 'code_territoire', 'code_territoire');
    }

    public function incidents()
    {
        return $this->hasMany(Incident::class, 'code_zonesante', 'code_zonesante');
    }
}
