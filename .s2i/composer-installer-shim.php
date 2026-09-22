<?php

/**
 * Shim do instalador do Composer para o build no OpenShift.
 *
 * O assemble do s2i-php instala o Composer rodando o instalador oficial
 * (getcomposer.org/installer), que por sua vez baixa o composer.phar de
 * getcomposer.org. Atrás do proxy da rede da prefeitura esse download chega
 * corrompido e falha com "Failed to decode zlib stream".
 *
 * Este shim é apontado pela variável COMPOSER_INSTALLER no Dockerfile. Em vez
 * de baixar, ele apenas instala o composer.phar que já foi baixado de forma
 * resiliente (com retry e validação) durante o build da imagem.
 */

$cache = getenv('COMPOSER_PHAR_CACHE') ?: '/opt/composer-cache/composer.phar';
$destino = getcwd().'/composer.phar';

if (! is_file($cache)) {
    fwrite(STDERR, "ERRO: composer.phar pre-baixado nao encontrado em {$cache}\n");
    exit(1);
}

if (! copy($cache, $destino)) {
    fwrite(STDERR, "ERRO: falha ao copiar composer.phar para {$destino}\n");
    exit(1);
}

chmod($destino, 0755);

echo "Composer instalado a partir do cache local ({$cache}); download de getcomposer.org ignorado.\n";
