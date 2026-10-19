# HU-108 — Integrar com Protocolo Municipal

## Épica
**EP13 — Integrações**

## Situação na implementação
- **Status:** Não evidenciada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Não há cliente nem rota que registre o processo num sistema externo de protocolo municipal.

## História de Usuário
**Como** sistema, **quero** protocolar no sistema oficial de protocolo da prefeitura quando esse contrato existir. Esse envio não está no código.

## Atores e permissões
- Não há permissão, controller ou parâmetro de URL de protocolo municipal.

## Fluxo evidenciado
1. O número de protocolo do Viabiliza, o comprovante e a consulta pública de protocolo são gerados e lidos na própria base.
2. O recebimento REGIN grava o protocolo que a Junta enviou. Isso não abre chamada a um protocolo municipal.
3. Não há classe, fila ou listener de integração com sistema de protocolo da prefeitura.

## Regras de negócio evidenciadas
- Protocolo interno e protocolo REGIN não substituem um registro em sistema municipal de tramitação.

## Validações
- Não há validação de retorno de protocolo externo municipal.

## Critérios de aceite
- Nenhuma rota em uso envia o processo a um protocolo municipal externo.
- A existência de comprovante e de consulta de protocolo no portal não conta como essa integração.

## Divergências da redação anterior
O texto pedia centralizar a tramitação no protocolo oficial. O código protocola no próprio Viabiliza e recebe o protocolo da Junta; não há o sistema municipal de protocolo.

## Evidências
- Ausência de controller, serviço ou binding de protocolo municipal em `app/`.
- `app/Services/Solicitacao/ComprovanteProtocoloService.php` (documento interno)
- `app/Http/Controllers/Regin/ReginRecebeController.php` (protocolo da Junta, não protocolo municipal)

## Não evidenciado
- URL, credencial, fila de retentativa ou confirmação de registro em protocolo municipal.
