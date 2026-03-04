<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Province extends Model
{
    protected $table = 'provinces';
    protected $primaryKey = 'code_province';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['code_province', 'nom_province'];

    public function territoires()
    {
        return $this->hasMany(Territoire::class, 'code_province', 'code_province');
    }

    public function zonesSantes()
    {
        return $this->hasManyThrough(
            ZoneSante::class,
            Territoire::class,
            'code_province',    // FK on territoires
            'code_territoire',  // FK on zonesantes
            'code_province',
            'code_territoire'
        );
    }

    public function incidents()
    {
        return $this->hasMany(Incident::class, 'code_province', 'code_province');
    }

    public function users()
    {
        return $this->hasMany(User::class, 'code_province', 'code_province');
    }
}
