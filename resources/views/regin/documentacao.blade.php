<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Viabiliza — APIs de integração</title>
    <link rel="stylesheet" href="{{ asset('swagger-ui/swagger-ui.css') }}">
    <style>
        body { margin: 0; }
    </style>
</head>
<body>
<div id="swagger-ui"></div>
<script src="{{ asset('swagger-ui/swagger-ui-bundle.js') }}"></script>
<script src="{{ asset('swagger-ui/swagger-ui-standalone-preset.js') }}"></script>
<script>
    window.ui = SwaggerUIBundle({
        urls: [
            { url: '/api_integracao/docs/openapi.yaml', name: 'REGIN — recebimento do processo' },
            { url: '/api_integracao/tvl/docs/openapi.yaml', name: 'SEFAZ — consulta de TVL' },
        ],
        'urls.primaryName': 'REGIN — recebimento do processo',
        dom_id: '#swagger-ui',
        deepLinking: true,
        presets: [SwaggerUIBundle.presets.apis, SwaggerUIStandalonePreset],
        layout: 'StandaloneLayout',
    });
</script>
</body>
</html>
