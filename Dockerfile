FROM php:8.2-apache-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends libonig-dev \
    && docker-php-ext-install pdo_mysql mbstring \
    && rm -rf /var/lib/apt/lists/*
COPY deploy/apache.conf /etc/apache2/conf-enabled/cred.conf
COPY deploy/start.sh /usr/local/bin/start-cred.sh
WORKDIR /var/www/html
COPY index.php index.html styles.css cred.js manifest.webmanifest sw.js schema.sql .htaccess ./
COPY assets/ assets/
COPY lib/ lib/
COPY scripts/ scripts/
COPY storage/.htaccess storage/.gitkeep storage/
ENV CRED_HTTPS=1
EXPOSE 80
CMD ["sh", "/usr/local/bin/start-cred.sh"]
