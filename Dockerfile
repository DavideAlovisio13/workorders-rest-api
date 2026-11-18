# Work Orders REST API — PHP CLI with pdo_sqlite (bundled in the official image).
FROM php:8.3-cli

WORKDIR /app
COPY . /app
RUN mkdir -p /app/data && php bin/seed.php

EXPOSE 8080
# Route every request through the front controller.
CMD ["php", "-S", "0.0.0.0:8080", "public/index.php"]
