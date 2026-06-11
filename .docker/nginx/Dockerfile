FROM nginx:1.25-alpine

LABEL maintainer="Notification Service Team"
LABEL description="Nginx for Laravel Notification Service"

RUN apk add --no-cache \
    curl \
    tzdata \
    && rm -rf /var/cache/apk/*

ENV TZ=UTC

# Создаем директории и настраиваем права ДО копирования конфигов
RUN mkdir -p /var/cache/nginx /var/run /var/log/nginx && \
    chown -R nginx:nginx /var/cache/nginx && \
    chown -R nginx:nginx /var/run && \
    chown -R nginx:nginx /var/log/nginx && \
    chmod 755 /var/run /var/log/nginx

# Создаем лог-файлы заранее
RUN touch /var/log/nginx/access.log /var/log/nginx/error.log && \
    chown nginx:nginx /var/log/nginx/access.log /var/log/nginx/error.log

# Создаем директорию для приложения
RUN mkdir -p /var/www/public && \
    chown -R nginx:nginx /var/www

# Копируем конфиги
COPY .docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY .docker/nginx/default.conf /etc/nginx/conf.d/default.conf

# Проверяем конфигурацию
# RUN nginx -t

# Переключаемся на пользователя nginx
# USER nginx

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=10s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

STOPSIGNAL SIGQUIT

CMD ["nginx", "-g", "daemon off;"]