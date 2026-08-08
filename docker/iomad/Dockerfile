ARG COMPOSER_IMAGE=composer:2.8.10
ARG PHP_IMAGE=php:8.3-fpm-bookworm
FROM ${COMPOSER_IMAGE} AS composer

FROM ${PHP_IMAGE}

ARG PHPREDIS_VERSION=6.3.0
ARG IOMAD_REPO=https://github.com/iomad/iomad.git
ARG IOMAD_REF=IOMAD_501_STABLE
ARG IOMAD_COMMIT=55b3128b8058d27f6cc4320850ca709ed5a792a9
ARG INCLUDE_IOMAD_SOURCE=true

LABEL org.opencontainers.image.source="https://github.com/hardikidea/iomad_learning" \
      org.opencontainers.image.revision="${IOMAD_COMMIT}" \
      org.opencontainers.image.version="${IOMAD_REF}"

ENV IOMAD_DIR=/var/www/iomad \
    IOMAD_DATAROOT=/var/www/iomaddata

RUN apt-get update \
    && apt-get install --no-install-recommends -y \
        ca-certificates \
        curl \
        git \
        libcurl4-openssl-dev \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libpq-dev \
        libxml2-dev \
        libzip-dev \
        nginx \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        curl \
        exif \
        gd \
        intl \
        mbstring \
        opcache \
        pgsql \
        pdo_pgsql \
        soap \
        zip \
    && pecl install "redis-${PHPREDIS_VERSION}" \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/iomad

RUN if [ "${INCLUDE_IOMAD_SOURCE}" = "true" ]; then \
        git init /var/www/iomad; \
        git -C /var/www/iomad remote add origin "${IOMAD_REPO}"; \
        git -C /var/www/iomad fetch --depth=1 origin "${IOMAD_COMMIT}"; \
        git -C /var/www/iomad checkout --detach FETCH_HEAD; \
        test "$(git -C /var/www/iomad rev-parse HEAD)" = "${IOMAD_COMMIT}"; \
    else \
        mkdir -p /var/www/iomad; \
    fi

COPY iomad-overrides/ /tmp/iomad-overrides/
COPY scripts/apply-iomad-overrides.sh /usr/local/bin/apply-iomad-overrides

RUN if [ "${INCLUDE_IOMAD_SOURCE}" = "true" ]; then \
        /usr/local/bin/apply-iomad-overrides --exclude-upstream \
            /tmp/iomad-overrides /var/www/iomad; \
        composer install --working-dir=/var/www/iomad \
            --no-dev --no-interaction --prefer-dist --optimize-autoloader; \
        rm -rf /var/www/iomad/.git; \
    fi \
    && rm -rf /tmp/iomad-overrides

COPY docker/iomad/config.php /var/www/iomad/config.php
COPY docker/iomad/php.ini /usr/local/etc/php/conf.d/iomad.ini
COPY docker/iomad/default.conf /etc/nginx/conf.d/default.conf
COPY docker/iomad/nginx.conf /etc/nginx/nginx.conf
COPY docker/iomad/entrypoint.sh /usr/local/bin/iomad-entrypoint

RUN mkdir -p /var/www/iomaddata /var/lib/nginx /var/log/nginx /run/nginx \
    && chown -R www-data:www-data \
        /var/www/iomad /var/www/iomaddata /var/lib/nginx /var/log/nginx /run/nginx \
    && chmod 0755 /usr/local/bin/iomad-entrypoint /usr/local/bin/apply-iomad-overrides

EXPOSE 8080

ENTRYPOINT ["iomad-entrypoint"]
CMD ["iomad-web"]
