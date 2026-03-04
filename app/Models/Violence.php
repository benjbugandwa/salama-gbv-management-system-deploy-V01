<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Violence extends Model
{
    protected $table = 'violences';
    public $timestamps = false;

    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'violence_name',
        'categorie_name',
        'violence_description',
    ];

    public function incidents()
    {
        return $this->belongsToMany(Incident::class, 'violence_incidents', 'id_violence', 'id_incident')
            ->withPivot(['id', 'description_violence', 'created_by', 'created_at']);
    }
}
