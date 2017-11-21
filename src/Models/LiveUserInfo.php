<?php

namespace Slimkit\PlusLive\Models;

use Zhiyi\Plus\Models\User;
use Illuminate\Database\Eloquent\Model;

class LiveUserInfo extends Model
{
    public function user () 
    {
        return $this->hasOne(User::class, 'id', 'uid');
    }	
}