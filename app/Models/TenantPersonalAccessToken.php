<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken;

class TenantPersonalAccessToken extends PersonalAccessToken
{
    protected $connection = 'tenant'; // টেনান্ট DB ব্যবহার করবে
}
