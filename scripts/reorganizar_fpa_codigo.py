import json

def reorganize():
    with open('docs/contagem-pf/contagem-sile-codigo.json', 'r', encoding='utf-8') as f:
        data = json.load(f)

    # Extrair todos os ALIs e AIEs
    alis = {}
    aies = {}
    
    for g in data['grupos']:
        if "ALIs" in g['nome']:
            for item in g['itens']:
                # Nome vem como "Dados - ModelName"
                nome_base = item['nome'].replace('Dados - ', '')
                alis[nome_base] = item
        elif "AIEs" in g['nome']:
            for item in g['itens']:
                nome_base = item['nome'].replace('Dados Externos - ', '')
                aies[nome_base] = item

    novos_grupos = []
    
    # Adicionar AIEs primeiro (ou como um grupo separado, mas geralmente ficam num grupo externo ou junto com os módulos)
    if aies:
        grupo_aies = {
            "id": "G_INTEGRACOES",
            "nome": "Integrações (AIEs)",
            "itens": list(aies.values())
        }
        novos_grupos.append(grupo_aies)

    # Para cada módulo/controller, vamos criar um grupo funcional
    for g in data['grupos']:
        if "Módulo:" in g['nome']:
            nome_modulo = g['nome'].split("Módulo: ")[1]
            
            itens_grupo = []
            
            # Se tivermos um ALI correspondente ao nome do módulo, colocamos ele como o primeiro item do grupo
            # Exemplo: Módulo "Company" -> ALI "Company"
            if nome_modulo in alis:
                itens_grupo.append(alis[nome_modulo])
                del alis[nome_modulo] # Remover para não sobrar depois
            
            # Adicionar as transações do módulo
            for item in g['itens']:
                itens_grupo.append(item)
                
            if itens_grupo:
                novos_grupos.append({
                    "id": g['id'],
                    "nome": f"Funcionalidade: {nome_modulo}",
                    "itens": itens_grupo
                })
                
    # Se sobrar algum ALI que não teve um Controller correspondente com o exato mesmo nome,
    # agrupamos em "Outros Dados" ou criamos grupos para eles
    if alis:
        grupo_sobras = {
            "id": "G_OUTROS_DADOS",
            "nome": "Dados e Entidades Auxiliares (ALIs)",
            "itens": list(alis.values())
        }
        novos_grupos.append(grupo_sobras)

    data['grupos'] = novos_grupos

    with open('docs/contagem-pf/contagem-sile-codigo-organizado.json', 'w', encoding='utf-8') as f:
        json.dump(data, f, indent=2, ensure_ascii=False)

if __name__ == "__main__":
    reorganize()
