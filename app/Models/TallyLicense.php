<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TallyLicense extends Model
{
    use HasFactory;
    protected $primaryKey = 'license_id';
    protected $guarded = [];
}
