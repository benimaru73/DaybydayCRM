<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reduction extends Model
{
    protected $table = 'reductions';

    protected $fillable = ['id','taux'];

    public $timestamps = true;


}