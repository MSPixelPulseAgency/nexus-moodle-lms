# Moodle 4.5 LTS on PHP 8.3 and Apache. All runtime state is held in named volumes.
FROM php:8.3-apache-bookworm

# Exact upstream revision running on Nexus production (Moodle 4.5.12+, build 20260728).
# Override explicitly only when performing a reviewed Moodle core update.
ARG MOODLE_REF=136359a52b388a138abaa3766eb8dbe7ec824962

ENV APACHE_DOCUMENT_ROOT=/var/www/moodle \
    MOODLE_DIR=/var/www/moodle \
    MOODLE_DATA_DIR=/var/www/moodledata \
    MOODLE_SOURCE_DIR=/usr/src/moodle

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        git \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libxml2-dev \
        libzip-dev \
        mariadb-client \
        rsync \
        unzip \
        zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd intl mbstring mysqli opcache soap zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && a2enmod expires headers rewrite \
    && rm -rf /var/lib/apt/lists/*

# Download the production-matched Moodle 4.5 source during the image build. The entrypoint
# copies it to the persistent Moodle application volume only when that volume is empty.
RUN mkdir -p "${MOODLE_SOURCE_DIR}" "${MOODLE_DIR}" "${MOODLE_DATA_DIR}" \
    && curl -fsSL "https://github.com/moodle/moodle/archive/${MOODLE_REF}.tar.gz" \
        -o /tmp/moodle.tar.gz \
    && tar -xzf /tmp/moodle.tar.gz --strip-components=1 -C "${MOODLE_SOURCE_DIR}" \
    && rm /tmp/moodle.tar.gz \
    && chown -R www-data:www-data "${MOODLE_DIR}" "${MOODLE_DATA_DIR}"

COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php/conf.d/nexus-moodle.ini /usr/local/etc/php/conf.d/nexus-moodle.ini
COPY docker/config.php /usr/local/share/nexus-moodle/config.php
COPY docker/scripts/docker-entrypoint.sh /usr/local/bin/nexus-moodle-entrypoint
COPY docker/scripts/moodle-install.sh /usr/local/bin/nexus-moodle-install
COPY docker/scripts/configure-moodle.php /usr/local/bin/nexus-configure-moodle.php
COPY docker/scripts/moodle-cron.sh /usr/local/bin/nexus-moodle-cron

RUN chmod +x /usr/local/bin/nexus-moodle-entrypoint \
    /usr/local/bin/nexus-moodle-install \
    /usr/local/bin/nexus-moodle-cron

WORKDIR /var/www/moodle
ENTRYPOINT ["/usr/local/bin/nexus-moodle-entrypoint"]
CMD ["apache2-foreground"]
