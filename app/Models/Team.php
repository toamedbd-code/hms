<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Jetstream\Team as JetstreamTeam;

class Team extends JetstreamTeam
{
    use HasFactory;

    protected $table = 'teams';

    protected $fillable = [
        'user_id',
        'name',
        'personal_team',
    ];

    // Owner and users relationships are provided by Laravel\Jetstream\Team
}
