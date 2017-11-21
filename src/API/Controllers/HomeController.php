<?php

namespace Slimkit\PlusLive\API\Controllers;

class HomeController
{
    public function index()
    {
        return trans('plus-live::messages.success');
    }

    public function rooms () {
        return 'hehehe';
    }
}
