#!/usr/bin/env python3
"""Publica no SEMIT (homologa e/ou main) o que mudou no repositório de trabalho.

Uso, a partir da raiz do repositório de trabalho, com a VPN da prefeitura ligada:
  python3 scripts/publicar-semit.py                 # homologa + main, tag PATCH
  python3 scripts/publicar-semit.py --destino homologa
  python3 scripts/publicar-semit.py --minor         # tag MINOR em vez de PATCH
  python3 scripts/publicar-semit.py --dry-run       # monta e valida, não envia
  python3 scripts/publicar-semit.py --base <commit> # sobrescreve o ponto de partida

Fluxo: lê o último commit publicado (refs/semit/publicado, só local), monta o
diff base..HEAD dos arquivos que o SEMIT recebe, aplica sobre um clone temporário
do SEMIT, refaz o build do frontend quando preciso, varre referências proibidas,
sobe a VERSION, comita, cria a tag anotada vX.Y.Z e envia. Nunca faz
`git push semit` a partir deste repositório e nunca usa --force.

Só entra o que está commitado. Alterações soltas no working tree ficam de fora.
"""

from __future__ import annotations

import argparse
import importlib.util
import re
import shutil
import subprocess
import sys
import tempfile
from pathlib import Path

REF_PUBLICADO = "refs/semit/publicado"
RAIZ = Path(__file__).resolve().parent.parent
MONTAR = RAIZ / ".cursor/skills/publicar-softwell-semit/scripts/montar-entrega-semit.py"
BRANCHES = {"homologa": ["homologa"], "main": ["main"], "ambos": ["homologa", "main"]}
FRONTEND = re.compile(r"^(resources/(js|css)/|package(-lock)?\.json$|vite\.config|tsconfig)")
SEM_BUILD = ("public/build/",)


def sh(args: list[str], cwd: Path | None = None, check: bool = True, entrada: str | None = None) -> str:
    r = subprocess.run(args, cwd=cwd, input=entrada, text=True, capture_output=True)
    if check and r.returncode != 0:
        saida = ((r.stdout or "") + (r.stderr or "")).strip()
        raise SystemExit(f"Falhou: {mascarar(' '.join(args[:3]))}\n{mascarar(saida)}")
    return r.stdout.strip()


def mascarar(texto: str) -> str:
    return re.sub(r"(https?://)[^/@\s]+@", r"\1", texto)


def carregar_regras():
    spec = importlib.util.spec_from_file_location("montar_semit", MONTAR)
    modulo = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(modulo)
    return modulo


def excluido(caminho: str, regras) -> bool:
    if caminho in regras.REMOVE_FILES or caminho in SELF_EXCLUDE:
        return True
    if any(caminho == d or caminho.startswith(d + "/") for d in regras.REMOVE_DIRS + [".claude", ".worktrees", ".superpowers", ".code-review-graph", ".playwright-cli"]):
        return True
    return caminho.startswith(SEM_BUILD)


SELF_EXCLUDE = {"scripts/publicar-semit.py", ".phpunit.result.cache"}


def proxima_tag(tags: list[str], minor: bool) -> str:
    versoes = [tuple(map(int, m.groups())) for t in tags if (m := re.fullmatch(r"v(\d+)\.(\d+)\.(\d+)", t))]
    if not versoes:
        return "v0.1.0"
    a, b, c = max(versoes)
    return f"v{a}.{b + 1}.0" if minor else f"v{a}.{b}.{c + 1}"


def main() -> int:
    p = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    p.add_argument("--destino", choices=BRANCHES, default="ambos")
    p.add_argument("--minor", action="store_true", help="tag MINOR (funcionalidade); padrão é PATCH")
    p.add_argument("--base", help="commit de partida (padrão: refs/semit/publicado)")
    p.add_argument("--dry-run", action="store_true", help="monta e valida sem enviar")
    p.add_argument("-y", "--yes", action="store_true", help="não pedir confirmação")
    args = p.parse_args()

    regras = carregar_regras()
    head = sh(["git", "rev-parse", "HEAD"], RAIZ)
    base = args.base or sh(["git", "rev-parse", "--verify", "-q", REF_PUBLICADO], RAIZ, check=False)
    if not base:
        raise SystemExit(f"Sem {REF_PUBLICADO}. Informe o último commit publicado com --base <commit>.")
    base = sh(["git", "rev-parse", base], RAIZ)
    if base == head:
        print("Nada a publicar: HEAD já é o último commit publicado.")
        return 0
    if subprocess.run(["git", "merge-base", "--is-ancestor", base, head], cwd=RAIZ).returncode != 0:
        raise SystemExit("O último commit publicado não é ancestral de HEAD. Use --base com o commit certo.")

    url = sh(["git", "remote", "get-url", "semit"], RAIZ)
    if subprocess.run(["git", "ls-remote", "--heads", url], capture_output=True, text=True).returncode != 0:
        raise SystemExit("Não alcancei o SEMIT. Ligue a VPN/rede da prefeitura e rode de novo.")

    nomes = sh(["git", "diff", "--name-only", "--no-renames", base, head], RAIZ).splitlines()
    arquivos = [n for n in nomes if not excluido(n, regras)]
    if not arquivos:
        print("As mudanças desde o último publicado não afetam o SEMIT (só ferramentas de desenvolvimento).")
        if not args.dry_run:
            sh(["git", "update-ref", REF_PUBLICADO, head], RAIZ)
        return 0

    assuntos = sh(["git", "log", "--reverse", "--format=%s", f"{base}..{head}"], RAIZ).splitlines()
    solto = sh(["git", "status", "--porcelain", "--untracked-files=no"], RAIZ)
    print(f"Base {base[:8]} -> HEAD {head[:8]}: {len(arquivos)} arquivo(s) para o SEMIT, {len(assuntos)} commit(s).")
    if solto:
        print("Atenção: há alterações não commitadas; elas NÃO entram na publicação.")

    tmp = Path(tempfile.mkdtemp(prefix="publicar-semit-"))
    try:
        clone = tmp / "semit"
        sh(["git", "clone", "-q", "--depth", "1", "-b", "main", url, str(clone)])
        heads = dict(reversed(l.split()) for l in sh(["git", "ls-remote", "--heads", "origin"], clone).splitlines())
        if heads.get("refs/heads/main") != heads.get("refs/heads/homologa") and args.destino == "ambos":
            raise SystemExit("homologa e main do SEMIT estão em commits diferentes; publique por --destino.")
        tags = [l.split("refs/tags/")[1] for l in sh(["git", "ls-remote", "--tags", "origin"], clone).splitlines() if "^{}" not in l]
        tag = proxima_tag(tags, args.minor)
        if tag in tags:
            raise SystemExit(f"A tag {tag} já existe no SEMIT.")

        patch = sh(["git", "diff", "--binary", "--no-renames", base, head, "--", *arquivos], RAIZ) + "\n"
        (tmp / "mudanca.patch").write_text(patch)
        alvo = ["--directory=app-src"]
        direto = subprocess.run(["git", "apply", "--check", *alvo, str(tmp / "mudanca.patch")], cwd=clone, capture_output=True, text=True)
        if direto.returncode != 0:
            ja = subprocess.run(["git", "apply", "--check", "-R", *alvo, str(tmp / "mudanca.patch")], cwd=clone, capture_output=True, text=True)
            if ja.returncode == 0:
                print("O SEMIT já contém estas mudanças. Atualizo só o ponteiro local.")
                if not args.dry_run:
                    sh(["git", "update-ref", REF_PUBLICADO, head], RAIZ)
                return 0
            raise SystemExit("O diff não se aplica ao SEMIT (o arquivo divergiu):\n" + mascarar(direto.stderr))
        sh(["git", "apply", *alvo, str(tmp / "mudanca.patch")], clone)

        app = clone / "app-src"
        if any(FRONTEND.match(a) for a in arquivos):
            print("Frontend alterado: npm ci + build + teste do menu...")
            sh(["npm", "ci", "--silent"], app)
            sh(["npm", "run", "build"], app)
            sh(["npx", "vitest", "run", "resources/js/navigation/gestao-nav.test.ts"], app)
            shutil.rmtree(app / "node_modules", ignore_errors=True)

        achados = []
        for a in arquivos:
            f = app / a
            if f.is_file():
                try:
                    texto = f.read_text(encoding="utf-8")
                except UnicodeDecodeError:
                    continue
                achados += [f"{a}: {t}" for t in regras.FORBIDDEN if t in texto]
        if achados:
            raise SystemExit("Referências proibidas nos arquivos publicados:\n  " + "\n  ".join(achados))

        versao = tag[1:]
        (clone / "VERSION").write_text(versao + "\n")
        (app / "VERSION").write_text(versao + "\n")
        ator = ["-c", f"user.name={sh(['git', 'config', 'user.name'], RAIZ)}", "-c", f"user.email={sh(['git', 'config', 'user.email'], RAIZ)}"]
        mensagem = assuntos[-1] if len(assuntos) == 1 else f"fix: publica a versão {versao} do Viabiliza\n\n" + "\n".join(f"- {s}" for s in assuntos)
        destinos = BRANCHES[args.destino]
        corpo_tag = f"SEMIT {' e '.join(destinos)}.\n\n" + "\n".join(f"- {s}" for s in assuntos)
        sh(["git", "add", "-A"], clone)
        sh(["git", *ator, "commit", "-q", "-m", mensagem], clone)
        sh(["git", *ator, "tag", "-a", tag, "-m", corpo_tag], clone)

        print(f"\nPronto para enviar: {tag} -> {', '.join(destinos)}")
        for s in assuntos:
            print(f"  - {s}")
        if args.dry_run:
            print("\n--dry-run: nada foi enviado.")
            return 0
        if not args.yes and input("\nEnviar agora? [s/N] ").strip().lower() not in {"s", "sim", "y"}:
            print("Cancelado.")
            return 1

        sh(["git", "push", "origin", *[f"HEAD:{b}" for b in destinos], tag], clone)
        novo = sh(["git", "rev-parse", "HEAD"], clone)
        remoto = sh(["git", "ls-remote", "--heads", "origin"], clone)
        for b in destinos:
            if f"{novo}\trefs/heads/{b}" not in remoto:
                raise SystemExit(f"Conferência falhou: {b} não aponta para {novo[:8]}.")
        sh(["git", "update-ref", REF_PUBLICADO, head], RAIZ)
        print(f"\nPublicado: {tag} em {', '.join(destinos)} (commit {novo[:8]}).")
        print("Redeploy do ambiente SEMIT não faz parte deste script.")
        return 0
    finally:
        shutil.rmtree(tmp, ignore_errors=True)


if __name__ == "__main__":
    sys.exit(main())
