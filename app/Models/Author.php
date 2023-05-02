<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Author extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function threads(): HasMany
    {
        #return $this->hasMany(Thread::class, 'author_user_id', 'twitter_id');
        return $this->hasMany(Thread::class, 'author_id');

    }

}
