<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index(): string
    {
        echo ENVIRONMENT;

        return view('welcome_message');
    }
    public function about(): string
    {
        echo ENVIRONMENT;
        return "To jest podstrona o nas.";
    }
}
