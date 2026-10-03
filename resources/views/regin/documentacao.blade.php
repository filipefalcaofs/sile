<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Viabiliza — API de recebimento REGIN</title>
    <link rel="stylesheet" href="{{ asset('swagger-ui/swagger-ui.css') }}">
    <style>
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; }
        .viabiliza-topo { background: #0b3a53; color: #fff; padding: 20px 24px; }
        .viabiliza-topo h1 { margin: 0 0 4px; font-size: 18px; font-weight: 600; }
        .viabiliza-topo p { margin: 0; font-size: 13px; opacity: .85; }
        .viabiliza-topo a { color: #9fd3ea; }
    </style>
</head>
<body>
<header class="viabiliza-topo">
    <h1>Viabiliza — recebimento do processo de viabilidade de localização</h1>
    <p>
        SEDUR / Prefeitura do Salvador · URL municipal chamada pelo REGIN (JUCEB).
        Especificação em <a href="/api_integracao/docs/openapi.yaml">openapi.yaml</a>.
    </p>
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
</script>
</body>
</html>
