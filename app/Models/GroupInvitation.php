<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupInvitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'user_id',
        'status',
    ];

    // Relacja z grupą
    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    // Relacja z użytkownikiem
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
