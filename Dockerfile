FROM php:8.2-cli

# Instalar extensões e dependências
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    && docker-php-ext-install curl \
    && rm -rf /var/lib/apt/lists/*

# Criar diretório de trabalho
WORKDIR /app

# Copiar arquivo do proxy
COPY api_proxy.php .

# Criar diretório com permissão de escrita para cache
RUN chmod 777 /app

# Expor porta (Render usa variável $PORT)
EXPOSE 8080

# Iniciar servidor PHP com router
CMD php -S 0.0.0.0:${PORT:-8080} router.php
