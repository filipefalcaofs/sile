# Imagem web do Viabiliza no OKD. Base php-oracle da prefeitura,
# usuário 1001, assemble e build do frontend na própria imagem.
# A raiz do repositório no GitLab da prefeitura contém este arquivo;
# o código fica em app-src/.
FROM registry.salvador.ba.gov.br/library/php-oracle:8.4

USER 0
ADD app-src /tmp/src
RUN chown -R 1001:0 /tmp/src

# Contorna "Failed to decode zlib stream" no build: o assemble do s2i instala o
# Composer rodando o instalador oficial, que baixa o composer.phar de
# getcomposer.org sem retry. Atrás do proxy da rede da prefeitura esse
# download chega corrompido. Aqui baixamos o composer.phar direto do CDN com
# retry + validação e apontamos COMPOSER_INSTALLER para um shim que apenas
# instala esse phar (sem novo download).
RUN set -eux; \
    mkdir -p /opt/composer-cache; \
    ok=0; \
    for i in 1 2 3 4 5 6; do \
      if curl -fSL --retry 6 --retry-delay 5 \
           "https://getcomposer.org/download/latest-stable/composer.phar" \
           -o /opt/composer-cache/composer.phar \
         && php /opt/composer-cache/composer.phar --version >/dev/null 2>&1; then \
        ok=1; break; \
      fi; \
      echo "Tentativa $i de baixar/validar composer.phar falhou; aguardando..."; \
      sleep 5; \
    done; \
    [ "$ok" = "1" ] || { echo "ERRO: nao foi possivel obter um composer.phar valido"; exit 1; }; \
    cp /tmp/src/.s2i/composer-installer-shim.php /opt/composer-cache/installer-shim.php; \
    chmod -R a+rX /opt/composer-cache; \
    php /opt/composer-cache/composer.phar --version

# Limites alinhados ao php.ini da aplicação. Sem isso, o PHP da imagem S2I
# rejeita o upload antes da validação do Laravel.
RUN set -eux; \
    conf_dir="$(php -i 2>/dev/null | awk -F'=> ' '/^Scan this dir for additional .ini files/ {print $2; exit}')"; \
    if [ -z "$conf_dir" ] || [ ! -d "$conf_dir" ]; then \
      if [ -d /etc/php.d ]; then conf_dir=/etc/php.d; \
      elif [ -d /usr/local/etc/php/conf.d ]; then conf_dir=/usr/local/etc/php/conf.d; \
      else echo "ERRO: diretorio de conf PHP nao encontrado"; exit 1; fi; \
    fi; \
    printf '%s\n' \
      '; Viabiliza' \
      'upload_max_filesize = 64M' \
      'post_max_size = 64M' \
      'memory_limit = 256M' \
      'max_execution_time = 60' \
      'max_file_uploads = 50' \
      > "$conf_dir/99-app.ini"; \
    php -i | grep -E 'upload_max_filesize|post_max_size|memory_limit'

USER 1001

ENV COMPOSER_INSTALLER=file:///opt/composer-cache/installer-shim.php

RUN /usr/libexec/s2i/assemble
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
 && chmod -R ug+rwX storage bootstrap/cache
RUN npm install
RUN npm run build

CMD /usr/libexec/s2i/run
