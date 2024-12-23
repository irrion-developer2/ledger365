<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TallyBankAllocation extends Model
{
    use HasFactory;
    protected $primaryKey = 'bank_allocation_id';
    protected $guarded = [];
}
