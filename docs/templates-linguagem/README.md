# Templates `.gitignore` por linguagem

Esta pasta contém arquivos `.gitignore` **recomendados** por linguagem/stack para os casos em que o `.gitignore` raiz não cobre o suficiente.

O `.gitignore` raiz já traz os padrões essenciais para as linguagens mais comuns. Estes templates servem quando você quer:

- Substituir o `.gitignore` raiz por um focado apenas na sua linguagem.
- Anexar padrões adicionais específicos de um framework, build system ou ferramenta.

## Como usar

Existem duas opções:

### Opção 1 — Anexar ao `.gitignore` existente

```bash
cat docs/templates-linguagem/gitignore-<linguagem>.txt >> .gitignore
```

Útil para complementar o `.gitignore` raiz com padrões adicionais sem perder o que já existe.

### Opção 2 — Substituir o `.gitignore` raiz

```bash
cp docs/templates-linguagem/gitignore-<linguagem>.txt .gitignore
```

Útil em projetos monolíngues onde manter o `.gitignore` raiz multi-stack é ruído.

## Templates disponíveis

| Arquivo | Stack |
|---|---|
| `gitignore-node.txt` | Node.js / TypeScript / JavaScript |
| `gitignore-python.txt` | Python |
| `gitignore-go.txt` | Go |
| `gitignore-rust.txt` | Rust / Cargo |
| `gitignore-java.txt` | Java / Kotlin / Gradle / Maven |
| `gitignore-java-jhipster.txt` | Java / JHipster (Maven/Gradle + Node frontend) |
| `gitignore-php-laravel.txt` | PHP / Composer / Laravel |
| `gitignore-ruby-rails.txt` | Ruby / Bundler / Rails |
| `gitignore-dotnet.txt` | .NET / C# |
| `gitignore-dotnet-abp.txt` | .NET / ABP (backend + Angular/React UI) |
| `gitignore-elixir-phoenix.txt` | Elixir / Phoenix |
| `gitignore-swift-ios.txt` | Swift / iOS / Xcode |

## Origem

Os padrões são baseados nos templates oficiais do GitHub disponíveis em [github/gitignore](https://github.com/github/gitignore), mantendo apenas o que cobre 80% dos casos reais.

Para padrões muito específicos (ferramenta X, framework Y), consulte o repositório oficial e anexe manualmente.
