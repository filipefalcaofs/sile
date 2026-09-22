import os
import json
import re

MODELS_DIR = 'app/Models'
CONTROLLERS_DIR = 'app/Http/Controllers'
SERVICES_DIR = 'app/Services'

def parse_models():
    models = []
    if os.path.exists(MODELS_DIR):
        for f in os.listdir(MODELS_DIR):
            if f.endswith('.php'):
                name = f.replace('.php', '')
                models.append(name)
    return models

def parse_controllers():
    controllers = {}
    if os.path.exists(CONTROLLERS_DIR):
        for root, dirs, files in os.walk(CONTROLLERS_DIR):
            for f in files:
                if f.endswith('Controller.php'):
                    name = f.replace('Controller.php', '')
                    path = os.path.join(root, f)
                    with open(path, 'r', encoding='utf-8', errors='ignore') as file:
                        content = file.read()
                        # Procura métodos públicos (actions)
                        methods = re.findall(r'public\s+function\s+([a-zA-Z0-9_]+)\s*\(', content)
                        controllers[name] = methods
    return controllers

def parse_services():
    services = []
    if os.path.exists(SERVICES_DIR):
        for root, dirs, files in os.walk(SERVICES_DIR):
            for f in files:
                if f.endswith('Service.php') or f.endswith('Integration.php') or f.endswith('Client.php'):
                    services.append(f.replace('.php', ''))
    return services

def generate_fpa_json():
    models = parse_models()
    controllers = parse_controllers()
    services = parse_services()
    
    alis = {}
    for m in models:
        # Ignorar models técnicos
        if any(x in m for x in ['Log', 'Pivot', 'Token', 'Migration', 'Job', 'Session']):
            continue
        
        complexidade = "media" if m in ['ViabilityRequest', 'Company', 'User', 'Cnae', 'GeoFeature', 'RuleVersion'] else "baixa"
        td = 25 if complexidade == "media" else 15
        
        alis[m] = {
            "nome": f"Dados - {m}",
            "tipo": "ALI",
            "complexidade": complexidade,
            "td": td,
            "ar": 1,
            "observacoes": f"Arquivo lógico mantido pela aplicação. Mapeado do model {m}.php. Tabela relacional de negócio.",
            "citacao_cpm": "Parte 2 Cap 6."
        }
    
    aies = []
    for s in services:
        if any(x in s for x in ['Sefaz', 'Regin', 'Geo', 'Receita', 'Integracao', 'Louos', 'Rule']):
            aies.append({
                "nome": f"Integração/Dados Externos - {s}",
                "tipo": "AIE",
                "complexidade": "baixa",
                "td": 10,
                "ar": 1,
                "observacoes": f"Referência externa/motor de regras obtida via integração. Mapeado de {s}.php.",
                "citacao_cpm": "Parte 2 Cap 6."
            })
    
    grupos = []
    
    # Adicionar AIEs primeiro
    if aies:
        grupos.append({
            "id": "G_AIE",
            "nome": "Integrações (AIEs)",
            "itens": aies
        })
    
    # Processar controllers e associar ALIs
    for ctrl, methods in controllers.items():
        if ctrl == 'Controller': continue
        
        itens_grupo = []
        
        # Associar o ALI que tem o mesmo nome do Controller (ex: CompanyController -> Company)
        if ctrl in alis:
            itens_grupo.append(alis[ctrl])
            del alis[ctrl]
            
        for m in methods:
            if m in ['__construct', 'middleware', 'authorize', 'rules']: continue
            
            tipo, complexidade, td, ar = "EE", "baixa", 5, 1
            
            # Heurísticas de nomenclatura
            trans_nome = m
            if m in ['index', 'list', 'search', 'get']:
                tipo, td, ar, trans_nome = "CE", 6, 2, "Consultar / Listar"
            elif m == 'show':
                tipo, td, ar, trans_nome = "CE", 6, 2, "Detalhar (Consulta)"
            elif m in ['export', 'report', 'print', 'download', 'pdf']:
                tipo, complexidade, td, ar, trans_nome = "SE", "media", 8, 2, "Exportar / Relatório"
            elif m in ['store', 'save']:
                tipo, td, ar, trans_nome = "EE", 10, 2, "Incluir / Salvar"
            elif m == 'update':
                tipo, td, ar, trans_nome = "EE", 10, 2, "Alterar"
            elif m in ['destroy', 'delete']:
                tipo, td, ar, trans_nome = "EE", 10, 2, "Excluir"
            elif m == 'import':
                tipo, td, ar, trans_nome = "EE", 15, 2, "Importar"
            elif m == 'create':
                continue # Apenas formulário de criação (sem persistência)
            elif m == 'edit':
                continue # Apenas formulário de edição (sem persistência)
            
            itens_grupo.append({
                "nome": trans_nome if trans_nome != m else f"Ação: {m}",
                "tipo": tipo,
                "complexidade": complexidade,
                "td": td,
                "ar": ar,
                "observacoes": f"Processo elementar mapeado do método {m} no {ctrl}Controller.php.",
                "citacao_cpm": "Parte 2 Cap 7."
            })
            
        if itens_grupo:
            grupos.append({
                "id": f"G_{ctrl}",
                "nome": f"Funcionalidade: {ctrl}",
                "itens": itens_grupo
            })
            
    # ALIs que sobraram (não tinham controller exato com mesmo nome)
    if alis:
        grupos.append({
            "id": "G_OUTROS",
            "nome": "Cadastros Auxiliares (ALIs sem módulo direto)",
            "itens": list(alis.values())
        })

    json_output = {
      "identificacao": {
        "empresa": "Sudoeste Informática",
        "aplicacao": "Viabiliza — Sistema de Licenciamento Eletrônico da SEDUR",
        "projeto": "Contagem IFPUG - Código Fonte Atualizada",
        "dv_numero": "DV-SILE-003",
        "responsavel": "Equipe de Análise — Sudoeste Informática (via Análise de Código)",
        "versao": "1.1",
        "tipo_contagem": "desenvolvimento",
        "rs_por_pf": 700,
        "proposito": "Recontagem atualizada do Viabiliza extraindo ALIs a partir de Models, AIEs de Services, e Funções Transacionais de Controllers. Otimizado para refletir com precisão CRUDs (Telas/Funções) agrupadas com os arquivos lógicos. Segue IFPUG 4.3.1 (PT-BR)."
      },
      "termo": {
        "sistema": "Viabiliza — SISTEMA DE LICENCIAMENTO ELETRÔNICO",
        "nome_curto": "Viabiliza",
        "total_pf": 0,
        "data": "Salvador-BA, 17 de setembro de 2026",
        "cliente": "Prefeitura Municipal de Salvador",
        "cliente_linha2": "Secretaria Municipal de Desenvolvimento Urbano e Sustentabilidade — SEDUR",
        "cliente_contratante": "Prefeitura Municipal de Salvador",
        "processo_licitatorio": "A definir",
        "contrato": "A definir",
        "processo_administrativo": "A definir",
        "planilha_anexa": "contagem-atualizada-codigo.xlsx",
        "elaborado_por": "Equipe de Análise — Sudoeste Informática",
        "elaborado_cargo": "Analista de Pontos de Função"
      },
      "grupos": grupos,
      "escopo_macro": [
        "1. Levantamento automático de ALI baseado em Models (Laravel).",
        "2. Levantamento automático de AIE baseado em Integrações nos Services.",
        "3. Mapeamento de Transações (EE/CE/SE) em Módulos via Controllers."
      ],
      "proposta": {
        "objeto": "Contagem de pontos de função extraída estaticamente da base de código, revisada e organizada por tela/função.",
        "validade_dias": 30,
        "prazo_execucao_dias": 10,
        "condicoes_pagamento": "Pagamento único após entrega."
      }
    }
    
    os.makedirs('docs/contagem-pf', exist_ok=True)
    with open('docs/contagem-pf/contagem-atualizada.json', 'w', encoding='utf-8') as f:
        json.dump(json_output, f, indent=2, ensure_ascii=False)
        
    print("Contagem revisada gerada em docs/contagem-pf/contagem-atualizada.json")

if __name__ == "__main__":
    generate_fpa_json()
