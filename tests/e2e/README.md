# E2E Tests — Transbank WooCommerce Plugin

Tests end-to-end con Playwright que validan los flujos de pago del plugin Transbank sobre una instancia real de WooCommerce + MySQL corriendo en el devcontainer.

## Qué se valida actualmente

### Webpay Plus

| Test                     | Archivo                                                        | Descripción                                                                                                                                         |
| ------------------------ | -------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------- |
| Pago exitoso             | `webpay-plus/payment-validate/successful-payment.spec.js`      | Flujo completo: login → carrito → checkout → pago en Transbank → confirmación de orden                                                              |
| Lock previene duplicados | `webpay-plus/payment-validate/concurrent-lock-success.spec.js` | Simula dos requests retornando con el mismo token simultáneamente. Verifica que no se creen órdenes duplicadas                                      |
| Retry con lock ocupado   | `webpay-plus/payment-validate/concurrent-retry-success.spec.js`| Fuerza el timeout de `GET_LOCK` adquiriendo el lock externamente. Verifica que el reintento interno procesa la transacción correctamente             |
| Reintentos agotados      | `webpay-plus/payment-validate/concurrent-retry-failure.spec.js`| Duplica el request en el retorno. Ambos requests agotan los reintentos internos contra un lock externo. Verifica página de error sin duplicar la orden |

## Prerequisitos

- Devcontainer corriendo (`docker compose up` desde `.devcontainer/`).
- Plugin Webpay instalado y configurado en modo integración.
- Usuario de WordPress para iniciar sesión en el checkout. El devcontainer crea por defecto el administrador `admin` / `admin` (ver `.devcontainer/wp-setup.sh`); el login de WooCommerce acepta tanto username como email en el campo `CUSTOMER_EMAIL`.
- Navegadores de Playwright instalados.

## Setup

```bash
cd tests/e2e
pnpm install
pnpm setup
```

Copiar `.env.example` a `.env` y ajustar si es necesario:

```bash
cp .env.example .env
```

## Variables de entorno

| Variable            | Default                  | Descripción                     |
| ------------------- | ------------------------ | ------------------------------- |
| `BASE_URL`          | `http://localhost:8000`  | URL de la instancia WooCommerce |
| `CUSTOMER_EMAIL`    | `admin`                  | Username o email para login     |
| `CUSTOMER_PASSWORD` | `admin`                  | Contraseña del usuario anterior |
| `DB_HOST`           | `localhost`              | Host de MySQL                   |
| `DB_PORT`           | `3306`                   | Puerto de MySQL                 |
| `DB_USER`           | `wordpress`              | Usuario de MySQL                |
| `DB_PASSWORD`       | `wordpress`              | Contraseña de MySQL             |
| `DB_NAME`           | `wordpress`              | Nombre de la base de datos      |

## Ejecución

```bash
# Todos los tests
pnpm test

# Tests de Webpay Plus
pnpm test:webpay-plus

# Modo debug (Playwright Inspector)
pnpm test:debug
```

Todos los comandos corren en modo **headed** (con navegador visible).

## Ver resultados

Playwright genera un reporte HTML después de cada ejecución:

```bash
pnpm report
```

Cuando un test falla se guardan automáticamente en `test-results/`:

- **Screenshot** de la página al momento del fallo.
- **Trace** interactivo (se abre con `pnpm playwright show-trace <archivo.zip>`).
- **Video** de la ejecución completa del test.

## Estructura

```
tests/e2e/
├── specs/                        # Tests agrupados por medio de pago
│   └── webpay-plus/
│       └── payment-validate/     # Tests de validación de pago
├── helpers/                      # Funciones reutilizables
│   ├── checkout.js               # Login, carrito, checkout WooCommerce
│   ├── webpay-form.js            # Formulario de tarjeta Transbank
│   ├── database.js               # Queries a MySQL vía mysql2
│   ├── concurrent.js             # Helpers de concurrencia (interceptor, holdReturnRequests)
│   └── assertions.js             # Assertions reutilizables (expectOrderConfirmation, expectPaymentError, etc.)
├── playwright.config.js
├── package.json
├── .env.example
└── .env                          # (gitignored)
```

## Agregar nuevos tests

1. Crear una carpeta en `specs/` para el medio de pago (ej. `specs/oneclick/`).
2. Crear archivos `.spec.js` dentro de esa carpeta.
3. Reutilizar los helpers existentes o agregar nuevos en `helpers/`.
4. Agregar un script `test:<medio>` en `package.json` para ejecutar solo esa sección.

## Notas

- Los tests corren con `workers: 1` y `fullyParallel: false` porque comparten estado en la base de datos.
- El timeout general es de 120 segundos por test, dado que involucran redirecciones externas a Transbank.
- `database.js` se conecta a MySQL vía `mysql2` con queries parametrizadas. Los valores por defecto de conexión coinciden con los del `docker-compose.yml`.
