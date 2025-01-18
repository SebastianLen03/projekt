<?php 

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VersionedAnswer extends Model
{
    use HasFactory;

    protected $fillable = ['versioned_question_id', 'text', 'is_correct', 'expected_code', 'language'];

    public function question()
    {
        return $this->belongsTo(VersionedQuestion::class, 'versioned_question_id');
    }
}
