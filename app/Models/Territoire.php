<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Territoire extends Model
{
    protected $table = 'territoires';
    protected $primaryKey = 'code_territoire';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['code_territoire
    ', 'nom_territoire', 'code_province'];

    public function province()
    {
        return $this->belongsTo(Province::class, 'code_province', 'code_province');
    }

    public function zonesSantes()
    {
        return $this->hasMany(ZoneSante::class, 'code_territoire', 'code_territoire');
    }

    public function incidents()
    {
        return $this->hasMany(Incident::class, 'code_territoire', 'code_territoire');
    }
}
