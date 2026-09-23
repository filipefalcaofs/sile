#!/usr/bin/env python3
"""Monta app-src da entrega SEMIT a partir de um commit do repositório de trabalho.

Uso:
  python3 montar-entrega-semit.py <repo> <commit> <destino>

Cria <destino>/app-src e os Dockerfiles OKD na raiz de <destino>.
Apaga o que não pode ir ao cliente. Não reescreve PHP.
Código 0: árvore sem referências proibidas.
Código 2: árvore gerada, mas ainda há referências para corrigir no destino.
"""

from __future__ import annotations

import io
import shutil
import subprocess
import sys
import tarfile
from pathlib import Path

REMOVE_DIRS = [
    ".agents",
    ".ai",
    ".cursor",
    ".github",
    ".planning",
    "docs",
    "docker/okd",
]

# Arquivos da raiz do GitLab SEMIT. O app segue em app-src/.
OKD_ROOT_FILES = [
    ("docker/okd/Dockerfile", "Dockerfile"),
    ("docker/okd/DockerFile_supervisord", "DockerFile_supervisord"),
    ("docker/okd/DockerFile_schedule", "DockerFile_schedule"),
    ("docker/okd/.dockerignore", ".dockerignore"),
]

REMOVE_FILES = [
    "AGENTS.md",
    ".mcp.json",
    "boost.json",
    ".laravel-stack.json",
    ".repo-stack.json",
    "README.md",
    "Dockerfile",
    ".dockerignore",
    "docker-compose.yml",
    "docker-compose.portainer-full.yml",
    "docker-compose.portainer-infra.yml",
    "scripts/fpa_completo.py",
    "scripts/gerar_fpa_codigo.py",
    "scripts/reorganizar_fpa_codigo.py",
    "scripts/portainer-build-push.sh",
    "scripts/convert-cnae-xlsx.py",
    "app/Http/Controllers/Gestao/ReginProtocoloSimulacaoController.php",
    "app/Http/Requests/Gestao/SimularProtocoloReginRequest.php",
    "app/Services/Regin/ReginProtocoloSimulacaoService.php",
    "app/Services/Regin/ReginProtocoloCatalog.php",
    "app/Models/ReginSimulacaoExecucao.php",
    "app/Console/Commands/RiscoSimularProtocoloCommand.php",
    "app/Support/DemoMode.php",
    "database/factories/ReginSimulacaoExecucaoFactory.php",
    "database/migrations/2026_09_16_190000_create_regin_simulacao_execucoes_table.php",
    "database/migrations/2026_09_17_140000_add_viability_request_id_to_regin_simulacao_execucoes_table.php",
    "database/data/regras-20-08-26/protocolos-sedur.json",
    "database/seeders/DemonstracaoClienteSeeder.php",
    "database/seeders/DevAdminSeeder.php",
    "database/seeders/CompanySeeder.php",
    "database/seeders/SolicitacaoDevSeeder.php",
    "database/seeders/ZonaFicticiaDevSeeder.php",
    "database/seeders/ExpressoDevSeeder.php",
    "database/seeders/AnaliseDevSeeder.php",
    "database/seeders/ComunicacaoDevSeeder.php",
    "database/seeders/AuditoriaDevSeeder.php",
    "database/seeders/RelatoriosDevSeeder.php",
    "database/seeders/AiConfigDevSeeder.php",
    "resources/js/pages/gestao/risco/simulacao-regin.tsx",
    "tests/Feature/Risco/ReginProtocoloSimulacaoTest.php",
    "tests/Feature/Expresso/SimulacaoProtocoloFlagTest.php",
    "tests/Feature/Ai/AiConfigDevSeederTest.php",
    "tests/Feature/Seeders/DemonstracaoClienteSeederTest.php",
    "tests/Feature/Seeders/ExpressoSeedPostgisTest.php",
    "tests/Feature/Seeders/ComunicacaoDevSeederTest.php",
    "tests/Feature/Seeders/AuditoriaDevSeederTest.php",
    "tests/Feature/Seeders/AnaliseSeedPostgisTest.php",
]

FORBIDDEN = [
    ".cursor/",
    "Claude Code",
    "superpowers:",
    "cursor.com",
    "ReginProtocoloSimulacao",
    "simulacao-regin",
    "DemonstracaoClienteSeeder",
    "DevAdminSeeder",
    "ZonaFicticiaDevSeeder",
    "features.simulacao_protocolo",
    "filipefalcaofs",
    "gitlab.softwell.com.br",
]


def export_commit(repo: Path, commit: str, stage: Path) -> None:
    raw = subprocess.check_output(
        ["git", "-C", str(repo), "archive", "--format=tar", commit],
    )
    with tarfile.open(fileobj=io.BytesIO(raw)) as archive:
        archive.extractall(stage)


def install_okd_root(stage: Path, destino: Path) -> None:
    missing = [src for src, _dest in OKD_ROOT_FILES if not (stage / src).is_file()]
    if missing:
        print("Arquivos OKD ausentes no commit:", file=sys.stderr)
        for name in missing:
            print(name, file=sys.stderr)
        raise SystemExit(1)
    for src, dest_name in OKD_ROOT_FILES:
        shutil.copy2(stage / src, destino / dest_name)


def remove_excluded(stage: Path) -> None:
    for name in REMOVE_DIRS:
        path = stage / name
        if path.exists():
            shutil.rmtree(path)
    for name in REMOVE_FILES:
        path = stage / name
        if path.exists():
            path.unlink()
    scripts = stage / "scripts"
    if scripts.exists() and not any(scripts.iterdir()):
        scripts.rmdir()


def strip_regin_menu(stage: Path) -> None:
    nav = stage / "resources/js/navigation/gestao-nav.ts"
    if not nav.exists():
        return
    text = nav.read_text()
    text = text.replace(
        " * Antes de incluir, mover ou criar grupo: ler `.cursor/rules/menu-navegacao.mdc`.\n *\n",
        "",
    )
    item = (
        "            {\n"
        "                name: 'Simulação REGIN',\n"
        "                href: '/gestao/risco/simulacao-regin',\n"
        "                icon: 'plugin',\n"
        "                permission: 'consultar-cnaes',\n"
        "            },\n"
    )
    nav.write_text(text.replace(item, ""))


def scan(app_src: Path) -> list[str]:
    hits: list[str] = []
    for path in app_src.rglob("*"):
        if not path.is_file():
            continue
        if "public/build" in path.as_posix() or path.suffix == ".lock":
            continue
        try:
            text = path.read_text(encoding="utf-8")
        except (UnicodeDecodeError, OSError):
            continue
        for token in FORBIDDEN:
            if token in text:
                hits.append(f"{path}:{token}")
    return hits


def main() -> int:
    if len(sys.argv) != 4:
        print("Uso: montar-entrega-semit.py <repo> <commit> <destino>", file=sys.stderr)
        return 1

    repo = Path(sys.argv[1]).resolve()
    commit = sys.argv[2]
    destino = Path(sys.argv[3]).resolve()
    if destino.exists():
        shutil.rmtree(destino)
    stage = destino / "_stage"
    stage.mkdir(parents=True)

    export_commit(repo, commit, stage)
    install_okd_root(stage, destino)
    remove_excluded(stage)
    strip_regin_menu(stage)

    app_src = destino / "app-src"
    shutil.move(str(stage), str(app_src))

    hits = scan(app_src)
    if hits:
        print("Referências que ainda precisam sair da entrega SEMIT:")
        for hit in hits:
            print(hit)
        return 2

    print(f"entrega pronta em {app_src}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
