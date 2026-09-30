<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

$email = 'fillipe.falcao@sudoesteinformatica.com.br';
$senha = 'Viabiliza@2026';
$nome = 'Fillipe Falcão';

$raiz = raizDaAplicacao($argv[1] ?? null);

require $raiz.'/vendor/autoload.php';

$app = require $raiz.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$permissoes = [
    'acessar-gestao',
    'consultar-acessos-de-qualquer-conta',
    'manter-cnaes',
    'manter-gatilhos-risco',
    'manter-usuarios',
    'manter-perfis',
    'manter-parametros',
    'consultar-cnaes',
    'monitorar-emails',
    'consultar-territorio',
    'manter-territorio',
    'consultar-louos',
    'manter-louos',
    'registrar-contingencia',
    'atendimento-presencial',
    'consultar-solicitacoes',
    'manter-tipos-servico',
    'manter-tipos-imovel',
    'manter-requisitos-documentais',
    'analisar-processos',
    'distribuir-processos',
    'emitir-tvl',
    'encaminhar-malha-fina',
    'analisar-malha-fina',
    'enviar-tvl-analise',
    'preencher-ficha-vistoria',
    'manter-setores',
    'consultar-auditoria',
    'monitorar-lgpd',
    'gerenciar-alertas-abuso',
    'manter-config-email',
    'manter-config-ia',
    'consultar-relatorios',
    'relatorios.produtividade.nominal',
];

foreach ($permissoes as $permissao) {
    Permission::firstOrCreate(['name' => $permissao, 'guard_name' => 'web']);
}

$papel = Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);
$papel->givePermissionTo($permissoes);
app(PermissionRegistrar::class)->forgetCachedPermissions();

$usuario = User::query()->firstOrNew(['email' => $email]);
$usuario->name = $nome;

if (! $usuario->exists) {
    $usuario->cpf = cpfLivre();
}

$usuario->password = $senha;
$usuario->email_verified_at = now();
$usuario->inactivated_at = null;
$usuario->save();
$usuario->syncRoles(['administrador']);

$podeEntrar = Hash::check($senha, $usuario->password)
    && $usuario->can('acessar-gestao')
    && $usuario->hasVerifiedEmail()
    && ! $usuario->isInactive();

if (! $podeEntrar) {
    fwrite(STDERR, "Usuário gravado, mas o login no console não ficou liberado.\n");
    exit(1);
}

echo "Usuário {$usuario->email} (id {$usuario->id}) com perfil administrador.\n";

function raizDaAplicacao(?string $informada): string
{
    $candidatas = array_filter([
        $informada,
        getcwd(),
        '/opt/app-root/src',
        '/var/www/html',
    ]);

    foreach ($candidatas as $candidata) {
        if (is_file($candidata.'/artisan') && is_file($candidata.'/vendor/autoload.php')) {
            return $candidata;
        }
    }

    fwrite(STDERR, "Não achei a aplicação Laravel. Rode na pasta do pod ou passe o caminho: php criar-administrador-hml.php /opt/app-root/src\n");
    exit(1);
}

function cpfLivre(): string
{
    for ($tentativa = 0; $tentativa < 200; $tentativa++) {
        $base = str_pad((string) random_int(0, 999_999_999), 9, '0', STR_PAD_LEFT);
        $cpf = $base.digitoCpf($base).digitoCpf($base.digitoCpf($base));

        if (preg_match('/^(\d)\1{10}$/', $cpf) === 1) {
            continue;
        }

        if (! User::query()->where('cpf', $cpf)->exists()) {
            return $cpf;
        }
    }

    fwrite(STDERR, "Não encontrei um CPF livre.\n");
    exit(1);
}

function digitoCpf(string $base): string
{
    $soma = 0;
    $tamanho = strlen($base);

    for ($indice = 0; $indice < $tamanho; $indice++) {
        $soma += (int) $base[$indice] * (($tamanho + 1) - $indice);
    }

    return (string) (((10 * $soma) % 11) % 10);
}
