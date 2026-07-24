<?php

namespace Database\Seeders;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProfileSeeder extends Seeder
{
    public function run(): void
    {
        $profilesByEmail = [
            'superadmin@faithassistqr.test' => [
                'name' => 'Usuario',
                'paterno' => 'Superadmin',
                'materno' => 'Sistema',
            ],
            // 'coordinador@faithassistqr.test' => [
            //     'name' => 'Usuario',
            //     'paterno' => 'Coordinador',
            //     'materno' => 'Sistema',
            // ],
            // 'catequista@faithassistqr.test' => [
            //     'name' => 'Usuario',
            //     'paterno' => 'Capturador',
            //     'materno' => 'Sistema',
            // ],
            // 'capturista@faithassistqr.test' => [
            //     'name' => 'Usuario',
            //     'paterno' => 'Capturista',
            //     'materno' => 'Sistema',
            // ],
        ];

        foreach ($profilesByEmail as $email => $profileData) {
            $user = User::query()->where('email', $email)->first();

            if (! $user) {
                continue;
            }

            Profile::query()->updateOrCreate(
                ['user_id' => $user->id],
                $profileData
            );
        }

        User::query()
            ->whereDoesntHave('profile')
            ->get()
            ->each(function (User $user): void {
                [$name, $paterno, $materno] = $this->splitFullName($user->name);

                Profile::query()->create([
                    'name' => $name,
                    'paterno' => $paterno,
                    'materno' => $materno,
                    'user_id' => $user->id,
                ]);
            });
    }

    /**
     * Divide un nombre completo en nombre, paterno y materno.
     */
    private function splitFullName(string $fullName): array
    {
        $chunks = preg_split('/\s+/', trim($fullName)) ?: [];

        $name = $chunks[0] ?? 'Usuario';
        $paterno = $chunks[1] ?? 'SinApellido';
        $materno = isset($chunks[2]) ? implode(' ', array_slice($chunks, 2)) : null;

        return [$name, $paterno, $materno];
    }
}
