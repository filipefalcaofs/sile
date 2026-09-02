<?php

namespace App\Http\Requests\Portal;

use App\Enums\VirtualOfficeIntent;
use App\Models\Cnae;
use App\Models\VirtualOfficeActivityCnae;
use App\Models\VirtualOfficeInscriptionLock;
use App\Services\EscritorioVirtual\SedeAtividadesResolver;
use App\Services\Expresso\SedeEscritorioVirtualGatilho;
use App\Support\Settings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Instrução das atividades da solicitação (HU-064/HU-065): a atividade
 * principal e os CNAEs complementares vêm da tabela oficial e SÓ aceitam
 * CNAEs ativos (mesma regra da seleção manual da Fase 3, [03-06]). O limite
 * de complementares é administrável (HU-014) e lido dinamicamente do catálogo
 * (banco→cache→config), espelhando a validação dinâmica do [02-07].
 */
class UpdateSolicitacaoAtividadesRequest extends FormRequest
{
    /**
     * A autorização fina (dono + rascunho) é feita no controller via
     * Gate::authorize('update', ...) com a ViabilityRequestPolicy (08-05). A
     * rota já exige auth:web + verified + lgpd.accepted + ResolveRepresentation.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        $max = (int) Settings::get(
            'solicitacao.cnaes_complementares.max',
            config('sile.solicitacao.cnaes_complementares.max', 99),
        );

        return [
            // Exatamente uma atividade principal, da tabela oficial e ATIVA.
            'principal_cnae_id' => ['required', 'integer', Rule::exists('cnaes', 'id')->where('active', true)],
            // Complementares opcionais, até o limite parametrizável, sem duplicados e ATIVOS.
            'complementares' => ['nullable', 'array', "max:{$max}"],
            'complementares.*' => ['integer', 'distinct', Rule::exists('cnaes', 'id')->where('active', true)],
            // CNAEs marcados para EXCLUSÃO na alteração de atividade (RN-AA-05b).
            // Sem `active`, ao contrário dos complementares: uma atividade que a
            // empresa quer abandonar pode ter sido desativada no cadastro DEPOIS
            // de ela passar a exercê-la — exigir `active` a prenderia à
            // atividade obsoleta, impedindo justamente a saída que ela pede.
            'exclusoes' => ['nullable', 'array'],
            'exclusoes.*' => ['integer', 'distinct', Rule::exists('cnaes', 'id')],
            'confirma_perda_condicao_sede' => ['sometimes', 'nullable', 'boolean'],
            // Resposta da pergunta vinculada (RN-EV-01) — este passo é onde os
            // CNAEs são conhecidos, então é aqui que ela é perguntada, mesmo
            // escrevendo um campo do imóvel (wants_virtual_office_hq). O
            // preenchimento simétrico ao de SolicitacaoImovelController (ver
            // Task 3) evita que os dois passos briguem pelo mesmo campo.
            'wants_virtual_office_hq' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * A atividade principal nunca aparece entre os complementares (intenções
     * distintas — espelha a separação principal × secundários do [03-06]). E,
     * quando a inscrição já tem uma SEDE de escritório virtual ativa e a
     * intenção NÃO é constituir outra sede, a solicitação é ABRIGADA: todos os
     * CNAEs precisam estar na Lista EV vigente (RN-EV-05/CA-04). Quem constitui
     * sede numa inscrição já travada cai nos bloqueios de constituição de sede
     * (RN-C-01/02/03), não neste.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $primaryId = (int) $this->input('principal_cnae_id');

                if ($primaryId === 0) {
                    return;
                }

                $complementares = array_map('intval', (array) $this->input('complementares', []));

                if (in_array($primaryId, $complementares, true)) {
                    $validator->errors()->add('complementares', 'A atividade principal não pode estar entre os CNAEs complementares.');
                }
            },
            function (Validator $validator) {
                // Só se exclui o que a própria solicitação está submetendo
                // (principal ou complementar) — exclusoes não é um segundo
                // canal para mencionar CNAEs fora do escopo do passo.
                $exclusoes = array_map('intval', (array) $this->input('exclusoes', []));

                if ($exclusoes === []) {
                    return;
                }

                $submetidos = array_map('intval', array_merge(
                    [(int) $this->input('principal_cnae_id')],
                    (array) $this->input('complementares', []),
                ));

                foreach ($exclusoes as $id) {
                    if (! in_array($id, $submetidos, true)) {
                        $validator->errors()->add('exclusoes', 'Só é possível marcar para exclusão um CNAE que está entre os CNAEs submetidos.');

                        break;
                    }
                }
            },
            function (Validator $validator) {
                $solicitacao = $this->route('solicitacao');

                $inscricao = $solicitacao?->property_registration;

                // Só é abrigado se a inscrição existe E tem uma sede ativa; sem
                // sede ativa (ou inscrição vazia) não há restrição de Lista EV.
                //
                // "Inscrição com sede ativa" não implica mais "é abrigado": antes
                // de virtualOfficeIntent() existir, essa era a única premissa
                // disponível, mas quem está CONSTITUINDO a sede (intenção Sede)
                // também cai numa inscrição com sede ativa quando ela já é
                // duplicada — e não é abrigado nenhum. A lista do Anexo B é do
                // abrigado; a lista da sede é {gatilho} ∪ Anexo A, coberta pela
                // RN-C-03 no closure novo. Sem esta exclusão, os dois closures
                // somavam dois pareceres de causas diferentes (Anexo B + sede
                // duplicada) no mesmo campo para quem constitui sede.
                if (
                    blank($inscricao)
                    || ! VirtualOfficeInscriptionLock::ativoPara($inscricao)
                    || $solicitacao->virtualOfficeIntent() === VirtualOfficeIntent::Sede
                ) {
                    return;
                }

                // Recusa de abrigo em inscrição travada (Constituição §10.1,
                // RN-EV-01): quem respondeu "Não" à pergunta geral numa
                // inscrição com sede ativa é indeferido com orientação para se
                // abrigar — ANTES da validação de CNAEs do Anexo B, para não
                // somar dois pareceres de causas diferentes no mesmo erro.
                if (
                    $solicitacao->wants_virtual_office_tenant === false
                    && $solicitacao->virtualOfficeIntent() === VirtualOfficeIntent::Nenhum
                    && VirtualOfficeInscriptionLock::sedeAtiva($inscricao) !== null
                ) {
                    $validator->errors()->add(
                        'principal_cnae_id',
                        Settings::get(
                            'analise.escritorio_virtual.mensagem_recusa_abrigo',
                            config('sile.analise.escritorio_virtual.mensagem_recusa_abrigo'),
                        ),
                    );

                    return;
                }

                $ids = array_map('intval', array_merge(
                    [(int) $this->input('principal_cnae_id')],
                    (array) $this->input('complementares', []),
                ));
                $ids = array_values(array_filter($ids));

                if ($ids === []) {
                    return;
                }

                $codes = Cnae::query()->whereIn('id', $ids)->pluck('code');

                // O CNAE gatilho nunca consta do Anexo B (de propósito — RN-C-02),
                // então ele sempre reprova neste loop. Para quem tem intenção de
                // Abrigado, o parecer não pode ser o genérico de "fora da lista":
                // o decreto exige o texto que cita o CNAE e o Anexo B (achado 1
                // da revisão final — sem isto, o abrigado legítimo com sede ativa
                // nunca via o texto exigido, porque o closure irmão (RN-C-02)
                // para exatamente neste caso).
                $gatilho = $solicitacao->virtualOfficeIntent() === VirtualOfficeIntent::Abrigado
                    ? app(SedeEscritorioVirtualGatilho::class)->cnaeGatilho()
                    : null;

                foreach ($codes as $code) {
                    if (! VirtualOfficeActivityCnae::permitido($code)) {
                        if ($gatilho !== null && preg_replace('/\D/', '', $code) === $gatilho) {
                            $validator->errors()->add(
                                'principal_cnae_id',
                                str_replace(':cnae', $code, Settings::get(
                                    'analise.escritorio_virtual.mensagem_cnae_sede_em_abrigado',
                                    config('sile.analise.escritorio_virtual.mensagem_cnae_sede_em_abrigado'),
                                )),
                            );
                        } else {
                            $validator->errors()->add(
                                'principal_cnae_id',
                                Settings::get(
                                    'analise.escritorio_virtual.mensagem_bloqueio_abrigado',
                                    config('sile.analise.escritorio_virtual.mensagem_bloqueio_abrigado'),
                                ),
                            );
                        }

                        break;
                    }
                }
            },
            function (Validator $validator) {
                $solicitacao = $this->route('solicitacao');

                $intent = $solicitacao?->virtualOfficeIntent() ?? VirtualOfficeIntent::Nenhum;

                if ($intent === VirtualOfficeIntent::Nenhum) {
                    return;
                }

                $ids = array_map('intval', array_merge(
                    [(int) $this->input('principal_cnae_id')],
                    (array) $this->input('complementares', []),
                ));
                $ids = array_values(array_filter($ids));

                if ($ids === []) {
                    return;
                }

                $codes = Cnae::query()->whereIn('id', $ids)->pluck('code');

                // RN-C-02 (constituição de sede): o CNAE gatilho (8211-3/00)
                // caracteriza a sede e, de propósito, não consta do Anexo B —
                // um abrigado que o peça é sempre indeferido. O closure
                // existente (validação geral do Anexo B) já cobre esse mesmo
                // CNAE, mas SÓ roda quando a inscrição tem sede ativa
                // (`blank($inscricao) || ! VirtualOfficeInscriptionLock::ativoPara($inscricao)`
                // no topo dele). Para não somar dois pareceres da mesma causa
                // no mesmo campo, este ramo cobre exatamente o complemento:
                // inscrição em branco ou sem sede ativa. Juntos, os dois
                // fecham o domínio inteiro sem se sobrepor.
                if ($intent === VirtualOfficeIntent::Abrigado) {
                    $inscricaoAbrigado = $solicitacao->property_registration;

                    if (filled($inscricaoAbrigado) && VirtualOfficeInscriptionLock::ativoPara($inscricaoAbrigado)) {
                        return;
                    }

                    $gatilho = app(SedeEscritorioVirtualGatilho::class)->cnaeGatilho();

                    foreach ($codes as $code) {
                        if (preg_replace('/\D/', '', $code) === $gatilho) {
                            $validator->errors()->add(
                                'principal_cnae_id',
                                str_replace(':cnae', $code, Settings::get(
                                    'analise.escritorio_virtual.mensagem_cnae_sede_em_abrigado',
                                    config('sile.analise.escritorio_virtual.mensagem_cnae_sede_em_abrigado'),
                                )),
                            );

                            return;
                        }
                    }

                    return;
                }

                // RN-C-01: inscrição que já tem sede ativa não pode receber
                // outra constituição de sede. Sem inscrição informada não há
                // como já existir sede vinculada — segue direto para RN-C-03.
                $inscricao = $solicitacao->property_registration;

                if (filled($inscricao)) {
                    $lock = VirtualOfficeInscriptionLock::sedeAtiva($inscricao);

                    // A própria sede titular do vínculo precisa poder alterar
                    // as próprias atividades, inclusive excluir o CNAE que a
                    // caracteriza (Task 3) — sem esta exceção, RN-C-01
                    // bloqueava a sede por já existir a sede que ela é.
                    // `titularDoVinculo()` é a mesma checagem de
                    // `FluxoExpressoService` (fonte única): quem NÃO é a
                    // titular continua bloqueada, guarda contra qualquer
                    // empresa constituir sede numa inscrição já travada por
                    // outra.
                    if ($lock !== null && ! app(SedeEscritorioVirtualGatilho::class)->titularDoVinculo($solicitacao, $lock)) {
                        $validator->errors()->add(
                            'principal_cnae_id',
                            Settings::get(
                                'analise.escritorio_virtual.mensagem_sede_duplicada',
                                config('sile.analise.escritorio_virtual.mensagem_sede_duplicada'),
                            ),
                        );

                        return;
                    }
                }

                // RN-C-03: a sede só pode exercer {CNAE gatilho} ∪ Anexo A —
                // um erro por CNAE reprovado, para identificar todos. Sem
                // versão vigente do Anexo A, a lista está indisponível (dado
                // ausente, não "nenhuma atividade permitida") — não bloqueia
                // o passo: segue para a análise decidir, honesto (achado 2 da
                // revisão final; mesmo princípio anti-fachada do
                // FluxoExpressoService).
                $resolver = app(SedeAtividadesResolver::class);

                if (! $resolver->listaDisponivel()) {
                    return;
                }

                $naoPermitidos = $resolver->naoPermitidos($codes);

                foreach ($naoPermitidos as $code) {
                    $validator->errors()->add(
                        'principal_cnae_id',
                        str_replace(':cnae', $code, Settings::get(
                            'analise.escritorio_virtual.mensagem_cnae_fora_anexo_a',
                            config('sile.analise.escritorio_virtual.mensagem_cnae_fora_anexo_a'),
                        )),
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'principal_cnae_id.required' => 'Selecione a atividade principal.',
            'principal_cnae_id.exists' => 'A atividade principal informada não está ativa na tabela oficial.',
            'complementares.max' => 'O número de CNAEs complementares excede o limite permitido.',
            'complementares.*.exists' => 'Há CNAE complementar inativo ou inexistente na seleção.',
            'complementares.*.distinct' => 'Há CNAE complementar duplicado na seleção.',
            'exclusoes.*.exists' => 'Há CNAE marcado para exclusão inexistente na tabela oficial.',
            'exclusoes.*.distinct' => 'Há CNAE duplicado na marcação de exclusão.',
        ];
    }
}
