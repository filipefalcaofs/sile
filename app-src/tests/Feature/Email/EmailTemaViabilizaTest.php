<?php

namespace Tests\Feature\Email;

use App\Enums\DecisionOutcome;
use App\Mail\EmailServerTestMail;
use App\Models\User;
use App\Notifications\AbrigadoDesvinculadoNotification;
use App\Notifications\GestaoResetPasswordQueued;
use App\Notifications\PendenciaExpiradaNotification;
use App\Notifications\PendenciaSolicitadaNotification;
use App\Notifications\PrazoVencendoNotification;
use App\Notifications\ProcessoEscalonadoNotification;
use App\Notifications\ResetPasswordQueued;
use App\Notifications\RespostaPendenciaNotification;
use App\Notifications\ResultadoExpressoNotification;
use App\Notifications\TllExercicioFaltanteNotification;
use App\Notifications\VerifyEmailQueued;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Markdown;
use Illuminate\Notifications\Messages\MailMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class EmailTemaViabilizaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: \Closure(): MailMessage}>
     */
    public static function mensagens(): array
    {
        $fabricas = [];
        foreach (['prazo vencendo', 'escalonado', 'convite', 'respondido', 'expirado', 'deferida', 'indeferida', 'desvinculado', 'tll', 'verificar e-mail', 'redefinir senha', 'redefinir senha gestão'] as $nome) {
            $fabricas[$nome] = [fn (): MailMessage => self::montar($nome)];
        }

        return $fabricas;
    }

    private static function montar(string $nome): MailMessage
    {
        $usuario = new User(['email' => 'a@sedur.test']);

        $verificacao = new VerifyEmailQueued;
        $verificacao->verificationUrl = 'https://viabiliza.test/email/verify/1/abc';
        $redefinicao = new ResetPasswordQueued('token');
        $redefinicao->resetUrl = 'https://viabiliza.test/reset-password/token';
        $redefinicaoGestao = new GestaoResetPasswordQueued('token');
        $redefinicaoGestao->resetUrl = 'https://viabiliza.test/gestao/reset-password/token';

        $mapa = [
            'prazo vencendo' => fn (): MailMessage => (new PrazoVencendoNotification(1, 'VIA-2026-000001', 'Prazo', 'Vence em 2 dias.', 'https://viabiliza.test/p/1'))->toMail($usuario),
            'escalonado' => fn (): MailMessage => (new ProcessoEscalonadoNotification(1, 'VIA-2026-000001', 'Escalonado', 'Detalhe.', 'https://viabiliza.test/p/1'))->toMail($usuario),
            'convite' => fn (): MailMessage => (new PendenciaSolicitadaNotification('VIA-2026-000001', 'Envie o IPTU.', 1))->toMail($usuario),
            'respondido' => fn (): MailMessage => (new RespostaPendenciaNotification('VIA-2026-000001', 1))->toMail($usuario),
            'expirado' => fn (): MailMessage => (new PendenciaExpiradaNotification(1, 'VIA-2026-000001', 'Envie o IPTU.', 'https://viabiliza.test/p/1'))->toMail($usuario),
            'deferida' => fn (): MailMessage => (new ResultadoExpressoNotification('VIA-2026-000001', DecisionOutcome::Deferida))->toMail($usuario),
            'indeferida' => fn (): MailMessage => (new ResultadoExpressoNotification('VIA-2026-000001', DecisionOutcome::Indeferida))->toMail($usuario),
            'desvinculado' => fn (): MailMessage => (new AbrigadoDesvinculadoNotification(1, 'VIA-2026-000001', '123456', 'https://viabiliza.test/p/1'))->toMail($usuario),
            'tll' => fn (): MailMessage => (new TllExercicioFaltanteNotification([2026]))->toMail($usuario),
            'verificar e-mail' => fn (): MailMessage => $verificacao->toMail($usuario),
            'redefinir senha' => fn (): MailMessage => $redefinicao->toMail($usuario),
            'redefinir senha gestão' => fn (): MailMessage => $redefinicaoGestao->toMail($usuario),
        ];

        return $mapa[$nome]();
    }

    #[DataProvider('mensagens')]
    public function test_cada_notificacao_usa_topo_selo_e_rodape_do_viabiliza(\Closure $montar): void
    {
        $html = (string) $montar()->render();

        $this->assertStringContainsString('cid:viabiliza-pin', $html);
        $this->assertStringContainsString('cid:logo-prefeitura', $html);
        $this->assertStringContainsString('cid:logo-sedur', $html);
        $this->assertStringContainsString('A viabilidade certa, no lugar certo', $html);
        $this->assertStringContainsString('class="selo selo-', $html);
        $this->assertStringContainsString('<h1', $html);
        $this->assertStringNotContainsString('# ', strip_tags($html));
        $this->assertStringContainsString('não responda a esta mensagem', $html);
        $this->assertStringContainsString('Prefeitura de Salvador', $html);
        $this->assertStringNotContainsString('laravel.com/img', $html);
    }

    public function test_quadro_de_dados_traz_o_protocolo(): void
    {
        $html = (string) (new ResultadoExpressoNotification('VIA-2026-000777', DecisionOutcome::Deferida))
            ->toMail(new User)->render();

        $this->assertStringContainsString('dados-rotulo', $html);
        $this->assertStringContainsString('VIA-2026-000777', $html);
        $this->assertStringContainsString('destaque-conteudo', $html);
    }

    public function test_email_de_teste_smtp_usa_o_mesmo_layout(): void
    {
        $html = (new EmailServerTestMail('a@sedur.test', 'SEDUR'))->render();

        $this->assertStringContainsString('cid:viabiliza-pin', $html);
        $this->assertStringContainsString('cid:logo-sedur', $html);
        $this->assertStringContainsString('servidor de e-mail do Viabiliza está operacional', $html);
    }

    public function test_notificacao_embute_as_imagens_do_topo_no_e_mail(): void
    {
        $email = new Email;
        foreach ((new ResultadoExpressoNotification('VIA-2026-000777', DecisionOutcome::Deferida))->toMail(new User)->callbacks as $callback) {
            $callback($email);
        }

        $nomes = array_map(fn ($parte) => $parte->getName(), $email->getAttachments());
        $this->assertEqualsCanonicalizing(['viabiliza-pin', 'logo-prefeitura', 'logo-sedur'], $nomes);
        foreach ($email->getAttachments() as $parte) {
            $this->assertSame('inline', $parte->getDisposition());
        }
    }

    public function test_email_de_teste_smtp_embute_as_imagens_do_topo(): void
    {
        $email = new Email;
        foreach ((new EmailServerTestMail('a@sedur.test', 'SEDUR'))->envelope()->using as $callback) {
            $callback($email);
        }

        $this->assertCount(3, $email->getAttachments());
    }

    public function test_versao_texto_nao_quebra(): void
    {
        $mensagem = (new ResultadoExpressoNotification('VIA-2026-000777', DecisionOutcome::Indeferida))->toMail(new User);
        $texto = (string) app(Markdown::class)->renderText($mensagem->markdown, $mensagem->data());

        $this->assertStringContainsString('VIA-2026-000777', $texto);
    }
}
