# backend-lapequenaislamypime

Backend REST en Laravel para La Pequena Isla MIPYME en Cuba. Incluye ecommerce de alimentos y productos generales, catalogo, carrito, pedidos, pagos con PayPal, facturas, correos, direcciones con coordenadas y gestion administrativa.

## Stack

- Laravel 12 sobre PHP 8.3.
- PostgreSQL con base de datos `backendisla`.
- Laravel Sanctum para login/register y tokens de API.
- PayPal Checkout Orders API para pagos en USD.
- Monedas `USD` y `CUP` con tasas `USD/CUP` gestionables por administrador.
- Docker Compose con `app`, `nginx`, `postgres` y `mailpit`.

## Arranque local con PHP y PostgreSQL instalado

Configura `.env` para usar tu PostgreSQL local:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=backendisla
DB_USERNAME=postgres
DB_PASSWORD=tu_password_local
SANCTUM_STATEFUL_DOMAINS=
```

Luego ejecuta:

```bash
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8000
```

API: `http://127.0.0.1:8000/api`

## Arranque con Docker

Para usar el PostgreSQL y Mailpit del `docker-compose.yml`, `.env` debe apuntar a los nombres internos de Docker:

```env
DB_HOST=postgres
MAIL_HOST=mailpit
```

Comandos:

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

API: `http://localhost:8000/api`

Mailpit: `http://localhost:8025`

Para procesar correos en cola durante desarrollo:

```bash
docker compose exec app php artisan queue:work
```

## Usuarios locales

Los usuarios se crean desde variables de entorno locales. No guardes credenciales reales en el repositorio.

Para crear un administrador durante `php artisan migrate --seed`, define en tu `.env`:

```env
ADMIN_NAME="Nombre del administrador"
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=un_password_seguro
```

Para crear un cliente demo local, opcionalmente define:

```env
SEED_DEMO_USERS=true
DEMO_CUSTOMER_EMAIL=cliente@example.com
DEMO_CUSTOMER_PASSWORD=un_password_seguro
```

## Variables importantes

```env
DB_DATABASE=backendisla
DB_USERNAME=postgres
DB_PASSWORD=

PAYPAL_MODE=sandbox
PAYPAL_CLIENT_ID=
PAYPAL_CLIENT_SECRET=
PAYPAL_WEBHOOK_ID=

FRONTEND_URL=http://localhost:5173
GOOGLE_MAPS_API_KEY=
```

## Autenticacion

La API devuelve tokens Bearer. En Postman o frontend usa:

```http
Authorization: Bearer TU_TOKEN
Accept: application/json
Content-Type: application/json
```

## Flujo de compra

1. El cliente se registra o inicia sesion.
2. Agrega productos al carrito. El carrito requiere autenticacion.
3. Crea el pedido desde su carrito y direccion.
4. Para PayPal, el pedido se crea en `USD`.
5. El frontend redirige o usa el approval link de PayPal.
6. Al capturar el pago, o al recibir webhook `PAYMENT.CAPTURE.COMPLETED`, se marca el pedido como pagado.
7. Se genera factura y se envia correo al cliente.

## Endpoints principales

- `POST /api/auth/register`
- `POST /api/auth/login`
- `POST /api/auth/forgot-password`
- `POST /api/auth/reset-password`
- `GET /api/categories`
- `GET /api/products?q=&category_id=&currency=USD|CUP`
- `GET /api/products/{slug}`
- `GET /api/cart`
- `POST /api/cart/items`
- `POST /api/checkout/orders`
- `POST /api/checkout/orders/{order}/paypal`
- `POST /api/checkout/paypal/capture`
- `GET /api/orders`
- `GET /api/orders/{order}`
- `POST /api/webhooks/paypal`

Admin:

- `api/admin/categories`
- `api/admin/products`
- `api/admin/users`
- `api/admin/discounts`
- `api/admin/exchange-rates`
- `api/admin/orders`
- `PATCH /api/admin/orders/{order}/status`

## Notas de negocio

- PayPal se procesa en USD. CUP queda para precios locales, conversion y visualizacion.
- Las tasas se guardan con historico y cada pedido toma un snapshot de la tasa `USD/CUP`.
- Los precios se almacenan en centavos para evitar errores de decimales.
- Las facturas se emiten solo cuando el pago queda confirmado.
- Las direcciones aceptan latitud/longitud para integracion posterior con Google Maps, Leaflet u otro proveedor.

## Despliegue recomendado

Para Hostinger, usar VPS, no hosting compartido, porque PostgreSQL necesita configuracion de servidor. En VPS se puede instalar Docker, levantar este `docker-compose.yml`, configurar SSL con Nginx/Caddy y apuntar el dominio.

Alternativa sencilla: DigitalOcean App Platform o Railway con Docker y PostgreSQL administrado. Es mas comodo para despliegues desde GitHub, pero suele salir mas caro que un VPS pequeno cuando sumas backend, worker y base de datos.
