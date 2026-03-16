# Operacao de Filas com Redis

## 1. Variaveis de ambiente (producao/homolog)

```env
QUEUE_CONNECTION=redis

QUEUE_MAIL_CONNECTION=redis
QUEUE_MAIL=emails

QUEUE_EXPORTS_CONNECTION=redis
QUEUE_EXPORTS=exports

QUEUE_NOTIFICATIONS_CONNECTION=redis
QUEUE_NOTIFICATIONS=default

REDIS_QUEUE_CONNECTION=default
REDIS_QUEUE=default
REDIS_QUEUE_BLOCK_FOR=5
REDIS_QUEUE_AFTER_COMMIT=true
```

## 2. Worker recomendado

```bash
php artisan queue:work redis --queue=emails,exports,default --sleep=1 --tries=3 --timeout=300
```

## 3. Deploy (apos atualizar .env)

```bash
php artisan optimize:clear
php artisan config:cache
php artisan queue:restart
```

## 4. Diagnostico rapido de e-mail nao enviado

Se o SMTP estiver correto no `.env` e o e-mail ainda nao sair:

1. Confirme config carregada:

```bash
php artisan tinker --execute="dump(config('mail.default'), config('queue.default'));"
```

2. Confirme worker em execucao:

```bash
php artisan queue:work redis --queue=emails,exports,default
```

3. Verifique falhas:

```bash
php artisan queue:failed
php artisan queue:retry all
```
