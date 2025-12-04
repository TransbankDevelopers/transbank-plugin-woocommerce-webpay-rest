############################
# image with
# WordPress 6.8.3
# Woocomerce 10.3.6
# Node 20.x
# Wordpress Cli lasted
# Composer 2.9.2
############################
FROM wordpress:6.8.3-php8.2-apache

ENV WORDPRESS_ROOT=/var/www/html
ENV COMPOSER_VERSION=2.9.2
ENV TZ=America/Santiago

# install utilities
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    less \
    git \
    mariadb-client \
    zip \
    wget \
    unzip \
    ca-certificates \
    netcat-openbsd \
    gnupg \
    libjpeg-dev \
    libpng-dev \
    libwebp-dev \
    libzip-dev \
    libxml2-dev \
 && docker-php-ext-install soap \
 && rm -rf /var/lib/apt/lists/*

# Time zone settings
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime \
 && echo "$TZ" > /etc/timezone \
 && echo "date.timezone = $TZ" > /usr/local/etc/php/conf.d/00-timezone.ini

# Logging PHP to file (Apache)
RUN printf '%s\n' \
  'log_errors=On' \
  'error_reporting=E_ALL' \
  'display_errors=Off' \
  'error_log=/var/log/apache2/php-error.log' \
  > /usr/local/etc/php/conf.d/99-logging.ini

# Avoid ServerName warning in Apache
RUN echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf \
 && a2enconf servername

# Install node 20.x
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
 && apt-get update \
 && apt-get install -y --no-install-recommends nodejs \
 && npm install -g npm@latest \
 && rm -rf /var/lib/apt/lists/*

# Install composer 2.9.2
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
 && php composer-setup.php --version=${COMPOSER_VERSION} --install-dir=/usr/local/bin --filename=composer \
 && php -r "unlink('composer-setup.php');" \
 && composer --version

# Install wordpress cli
RUN curl -o /usr/local/bin/wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
 && chmod +x /usr/local/bin/wp

# Change ownership to www-data in /var/www
RUN chown -R www-data:www-data /var/www && chmod -R 775 /var/www

# Copy the shell settings to make the shop available
COPY /.devcontainer/wp-setup.sh /usr/local/bin/wp-setup.sh
RUN chmod +x /usr/local/bin/wp-setup.sh \
 && ls -l /usr/local/bin/wp-setup.sh

# Set the workspace directory as a safe directory in Git
RUN git config --global --add safe.directory /workspace

WORKDIR ${WORDPRESS_ROOT}
