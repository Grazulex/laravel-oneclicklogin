<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;

if (! function_exists('createTestUser')) {
    function createTestUser(string $email): Authenticatable
    {
        $userClass = config('oneclicklogin.user_model');

        // If user model is configured and exists, use it
        if ($userClass && class_exists($userClass)) {
            return $userClass::create([
                config('oneclicklogin.email_field', 'email') => $email,
                'name' => 'Test User',
                'password' => bcrypt('password'),
            ]);
        }

        // Otherwise create a test user model
        return TestUser::create([
            'email' => $email,
            'name' => 'Test User',
            'password' => bcrypt('password'),
        ]);
    }
}

if (! class_exists('TestUser')) {
    class TestUser extends Authenticatable
    {
        protected $table = 'users';

        protected $fillable = ['email', 'name', 'password'];

        protected $hidden = ['password'];

        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthPassword(): string
        {
            return $this->password;
        }
    }
}
