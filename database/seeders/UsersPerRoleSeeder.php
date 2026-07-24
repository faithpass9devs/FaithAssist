<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class UsersPerRoleSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Usuario Superadmin',
                'email' => 'superadmin@faithassistqr.test',
                'whatsapp_phone' => '+5215511111111',
                'password' => 'password',
                'role' => 'Superadmin',
            ],
            // [
            //     'name' => 'Usuario Coordinador',
            //     'email' => 'coordinador@faithassistqr.test',
            //     'whatsapp_phone' => '+5215522222222',
            //     'password' => 'password',
            //     'role' => 'Coordinador',
            // ],
            // [
            //     'name' => 'Usuario Catequista',
            //     'email' => 'catequista@faithassistqr.test',
            //     'whatsapp_phone' => '+5215533333333',
            //     'password' => 'password',
            //     'role' => 'Catequista',
            // ],
            // [
            //     'name' => 'Usuario Capturista',
            //     'email' => 'capturista@faithassistqr.test',
            //     'whatsapp_phone' => '+5215544444444',
            //     'password' => 'password',
            //     'role' => 'Capturista',
            // ],
        ];

        foreach ($users as $userData) {
            $user = User::query()->updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'whatsapp_phone' => $userData['whatsapp_phone'],
                    'password' => $userData['password'],
                    'email_verified_at' => Carbon::now(),
                ]
            );

            $user->syncRoles([$userData['role']]);
        }
    }
}
