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
                        # Find public methods (actions)
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

def generate_fpa_json(models, controllers, services):
    
    grupos = []
    
    # 1. Map ALIs from Models
    # Try to group ALIs logically.
    model_ali_map = []
    for m in models:
        # Skip technical models
        if 'Log' in m or 'Pivot' in m or 'Token' in m or 'Migration' in m or 'Job' in m or 'Session' in m:
            continue
        
        # Base complexidade
        complexidade = "baixa"
        if m in ['ViabilityRequest', 'Company', 'User', 'Cnae']:
            complexidade = "media"
        
        model_ali_map.append({
            "nome": f"Dados - {m}",
            "tipo": "ALI",
            "complexidade": complexidade,
            "td": 15 if complexidade == "baixa" else 25,
            "ar": 1,
            "observacoes": f"Arquivo lógico mantido pela aplicação. Mapeado do model {m}.php. Classificação funcional baseada em tabela relacional autônoma.",
            "citacao_cpm": "Parte 2 Cap 6."
        })
    
    # 2. Map AIEs from Services
    aies = []
    for s in services:
        if 'Sefaz' in s or 'Regin' in s or 'Geo' in s or 'Receita' in s or 'Integracao' in s:
            aies.append({
                "nome": f"Dados Externos - {s}",
                "tipo": "AIE",
                "complexidade": "baixa",
                "td": 10,
                "ar": 1,
                "observacoes": f"Referência externa obtida através de integração. Mapeado do serviço {s}.php.",
                "citacao_cpm": "Parte 2 Cap 6."
            })
    
    # 3. Map Transactions from Controllers
    transacoes = []
    for ctrl, methods in controllers.items():
        if ctrl == 'Controller': continue
        
        for m in methods:
            if m in ['__construct', 'middleware', 'authorize']: continue
            
            tipo = "EE" # Default
            complexidade = "baixa"
            td = 5
            ar = 1
            
            nome_transacao = f"{ctrl} - {m}"
            
            if m in ['index', 'show', 'list', 'search', 'get']:
                tipo = "CE"
                td = 6
                ar = 2
            elif m in ['export', 'report', 'print', 'download', 'pdf']:
                tipo = "SE"
                td = 8
                ar = 2
                complexidade = "media"
            elif m in ['store', 'update', 'destroy', 'delete', 'import', 'upload', 'save']:
                tipo = "EE"
                td = 10
                ar = 2
            else:
                # Custom methods usually are EE (actions) or CE (queries). Let's assume EE for safety if not explicitly CE/SE
                tipo = "EE"
            
            transacoes.append({
                "nome": nome_transacao,
                "tipo": tipo,
                "complexidade": complexidade,
                "td": td,
                "ar": ar,
                "observacoes": f"Processo elementar mapeado do método {m} no controller {ctrl}Controller.php. Classificação heurística pelo verbo/ação.",
                "citacao_cpm": "Parte 2 Cap 7."
            })

    # Pack into Groups (Max 50 items per group to avoid huge groups, or group by Domain)
    # Let's put ALIs in one group, AIEs in another, and divide Transacoes by Controller chunks
    
    grupos.append({
        "id": "G_ALI",
        "nome": "1. Modelo de Dados Interno (ALIs)",
        "itens": model_ali_map
    })
    
    if aies:
        grupos.append({
            "id": "G_AIE",
            "nome": "2. Integrações e Dados Externos (AIEs)",
            "itens": aies
        })
    
    # Group transacoes by controller
    ctrl_groups = {}
    for t in transacoes:
        ctrl_name = t['nome'].split(' - ')[0]
        if ctrl_name not in ctrl_groups:
            ctrl_groups[ctrl_name] = []
        
        # Limpar o nome para o item final
        t['nome'] = t['nome'].split(' - ')[1]
        
        # Translate commonly to Portuguese
        trans = {
            'index': 'Consultar/Listar',
            'show': 'Detalhar',
            'store': 'Incluir',
            'update': 'Alterar',
            'destroy': 'Excluir'
        }
        if t['nome'] in trans:
            t['nome'] = trans[t['nome']]
            
        ctrl_groups[ctrl_name].append(t)
        
    for i, (ctrl_name, itens) in enumerate(ctrl_groups.items()):
        grupos.append({
            "id": f"G_CTRL_{i}",
            "nome": f"3.{i+1} Módulo: {ctrl_name}",
            "itens": itens
        })

    json_output = {
      "identificacao": {
        "empresa": "Sudoeste Informática",
        "aplicacao": "Viabiliza — Sistema de Licenciamento Eletrônico da SEDUR",
        "projeto": "Contagem IFPUG - Extração do Código Fonte",
        "dv_numero": "DV-SILE-002",
        "responsavel": "Equipe de Análise — Sudoeste Informática (via Análise de Código)",
        "versao": "1.0",
        "tipo_contagem": "desenvolvimento",
        "rs_por_pf": 700,
        "proposito": "Medir o tamanho funcional do Viabiliza extraindo ALIs a partir de Models, AIEs de Services de integração, e Funções Transacionais de Controllers e Rotas. A contagem adota heurísticas sobre a estrutura Laravel e segue as regras de fronteira IFPUG 4.3.1 (PT-BR)."
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
        "planilha_anexa": "contagem-sile-codigo-fonte.xlsx",
        "elaborado_por": "Equipe de Análise — Sudoeste Informática",
        "elaborado_cargo": "Analista de Pontos de Função"
      },
      "grupos": grupos,
      "escopo_macro": [
        "1. Levantamento automático de Arquivos Lógicos Internos (ALI) baseado nos Models do Laravel.",
        "2. Levantamento automático de Arquivos de Interface Externa (AIE) baseado em Integrações nos Services.",
        "3. Levantamento de Processos Elementares (EE, CE, SE) baseado nos métodos públicos (actions) de Controllers."
      ],
      "proposta": {
        "objeto": "Contagem de pontos de função extraída estaticamente da base de código (Models, Services, Controllers).",
        "validade_dias": 30,
        "prazo_execucao_dias": 10,
        "condicoes_pagamento": "Pagamento único após entrega e aceite da medição em código."
      }
    }
    
    with open('docs/contagem-pf/contagem-sile-codigo.json', 'w', encoding='utf-8') as f:
        json.dump(json_output, f, indent=2, ensure_ascii=False)
        
    print("Contagem baseada no código gerada em docs/contagem-pf/contagem-sile-codigo.json")

if __name__ == "__main__":
    models = parse_models()
    controllers = parse_controllers()
    services = parse_services()
    generate_fpa_json(models, controllers, services)
