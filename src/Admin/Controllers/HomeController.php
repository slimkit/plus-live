<?php

namespace Slimkit\PlusLive\Admin\Controllers;

class HomeController
{
    public function index()
    {
        return trans('plus-live::messages.success');
    }
}
