<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Viabiliza — API de recebimento REGIN</title>
    <link rel="stylesheet" href="{{ asset('swagger-ui/swagger-ui.css') }}">
</head>
<body>
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
