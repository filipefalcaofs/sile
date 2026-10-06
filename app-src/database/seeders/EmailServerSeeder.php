<?php

namespace Database\Seeders;

use App\Models\EmailServer;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Servidor SMTP de partida (Zoho Mail, caixa da Sudoeste usada no SIGVISA).
 *
 * A senha entra só na primeira carga e fica cifrada no banco. Uma senha já
 * gravada — inclusive a trocada na tela — não é sobrescrita no redeploy.
 */
class EmailServerSeeder extends Seeder
{
    use WithoutModelEvents;

    public const PASSWORD = 'projetoAtendimento#@123';

    public const HOST = 'smtp.zoho.com';

    public function run(): void
    {
        $server = EmailServer::query()->updateOrCreate(
            ['host' => self::HOST],
            [
                'name' => 'Zoho Mail (Sudoeste)',
                'driver' => 'smtp',
                'port' => 587,
                'encryption' => 'tls',
                'timeout' => 30,
                'username' => 'atendimento2@sudoesteinformatica.com.br',
                'from_address' => 'atendimento2@sudoesteinformatica.com.br',
                'from_name' => 'Viabiliza',
                'active' => true,
            ],
        );

        $gravada = DB::table('email_servers')->where('id', $server->id)->value('password');

        if ($gravada === null || $gravada === '') {
            $server->password = self::PASSWORD;
            $server->save();
        }

        $server->setAsDefault();
    }
}
