<?php

namespace Slimkit\PlusLive\Models;

use Zhiyi\Plus\Models\User;
use Illuminate\Database\Eloquent\Model;

class LivePreOrderToken extends Model
{
    protected $table = 'live_preorder_token';

    protected $fillable = ['token', 'uid', 'to_uid', 'disabled'];
}