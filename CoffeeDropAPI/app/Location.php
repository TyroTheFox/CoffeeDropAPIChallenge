<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'locations';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'postcode';

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'postcode',
        'open_Monday',
        'open_Tuesday',
        'open_Wednesday',
        'open_Thursday',
        'open_Friday',
        'open_Saturday',
        'open_Sunday',
        'closed_Monday',
        'closed_Tuesday',
        'closed_Wednesday',
        'closed_Thursday',
        'closed_Friday',
        'closed_Saturday',
        'closed_Sunday',
    ];
}
