<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Crea el primer usuario administrador en una instalación nueva. A
 * diferencia de `db:seed` (que agrega clientes/facturas de ejemplo pensados
 * para desarrollo/demo), esto es lo que hay que correr en el servidor de un
 * cliente real: deja el sistema con un solo usuario y nada más.
 */
class SetupInitialAdmin extends Command
{
    protected $signature = 'app:setup-admin';

    protected $description = 'Crea el primer usuario administrador (instalación nueva, sin datos de ejemplo).';

    public function handle(): int
    {
        if (User::query()->exists()) {
            $this->error('Ya existen usuarios en el sistema. Este comando es solo para la primera puesta en marcha.');
            $this->line('Para agregar más usuarios, iniciá sesión y andá a Usuarios.');

            return self::FAILURE;
        }

        $name = $this->ask('Nombre completo');
        $username = $this->ask('Usuario para iniciar sesión');
        $password = $this->secret('Contraseña (mínimo 8 caracteres)');

        $validator = Validator::make(
            ['name' => $name, 'username' => $username, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'username' => ['required', 'string', 'max:255'],
                'password' => ['required', 'string', 'min:8'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::create([
            'name' => $name,
            'username' => $username,
            'password' => $password,
            'role' => Role::Admin,
            'active' => true,
        ]);

        $this->info("Listo. Ya podés iniciar sesión como \"{$username}\".");
        $this->line('Después de entrar, revisá Configuración de la Empresa antes de facturar.');

        return self::SUCCESS;
    }
}
