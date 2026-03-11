<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RagDocument extends Model
{
    // Erlaubt das massenhafte Zuweisen dieser Spalten (Mass Assignment)
    protected $fillable = [
        'uuid',
        'title',
        'tag',
        'user_id'
    ];

    // Optional: Beziehung zum User-Model definieren
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
