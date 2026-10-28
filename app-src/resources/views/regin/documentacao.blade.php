<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Viabiliza — APIs de integração</title>
    <link rel="stylesheet" href="{{ asset('swagger-ui/swagger-ui.css') }}">
    <style>
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; }
        .viabiliza-topo { background: #0b3a53; color: #fff; padding: 20px 24px; }
        .viabiliza-topo h1 { margin: 0 0 4px; font-size: 18px; font-weight: 600; }
        .viabiliza-topo p { margin: 0; font-size: 13px; opacity: .85; }
        .viabiliza-topo a { color: #9fd3ea; }
        .viabiliza-topo label { display: inline-block; margin-top: 12px; font-size: 13px; }
        .viabiliza-topo select { margin-left: 8px; padding: 4px 8px; font-size: 13px; }
    </style>
</head>
<body>
<header class="viabiliza-topo">
    <h1>Viabiliza — APIs de integração</h1>
    <p>
        SEDUR / Prefeitura do Salvador · escolha a API no menu abaixo:
        recebimento pelo REGIN (JUCEB) ou consulta de TVL pela SEFAZ/SIGS.
        Especificações em <a href="/api_integracao/docs/openapi.yaml">REGIN</a>
        e <a href="/api_integracao/tvl/docs/openapi.yaml">TVL</a>.
    </p>
    <label>API
        <select id="viabiliza-api">
            <option value="/api_integracao/docs/openapi.yaml">REGIN — recebimento do processo</option>
            <option value="/api_integracao/tvl/docs/openapi.yaml">SEFAZ — consulta de TVL</option>
        </select>
    </label>
</header>
<div id="swagger-ui"></div>
<script src="{{ asset('swagger-ui/swagger-ui-bundle.js') }}"></script>
<script>
    window.ui = SwaggerUIBundle({
        url: '/api_integracao/docs/openapi.yaml',
        dom_id: '#swagger-ui',
        deepLinking: true,
        presets: [SwaggerUIBundle.presets.apis],
    });

    document.getElementById('viabiliza-api').addEventListener('change', function (evento) {
        window.ui.specActions.updateUrl(evento.target.value);
        window.ui.specActions.download(evento.target.value);
    });
</script>
</body>
</html>
