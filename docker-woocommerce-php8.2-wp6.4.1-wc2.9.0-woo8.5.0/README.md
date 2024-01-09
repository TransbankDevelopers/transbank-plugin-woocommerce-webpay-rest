![Woocommerce](https://woocommerce.com/wp-content/themes/woo/images/logo-woocommerce@2x.png)

# Woocommerce Docker para desarrollo

### PHP 8.2 + Wordpress 6.4.1 + Wordpress Cli 2.9.0 + Mysql 5.7 + Woocommerce 8.5.0

### Requerimientos

**MacOS:**

Instalar [Docker](https://docs.docker.com/docker-for-mac/install/), [Docker-compose](https://docs.docker.com/compose/install/#install-compose) y [Docker-sync](https://github.com/EugenMayer/docker-sync/wiki/docker-sync-on-OSX).

**Windows:**

Instalar [Docker](https://docs.docker.com/docker-for-windows/install/), [Docker-compose](https://docs.docker.com/compose/install/#install-compose) y [Docker-sync](https://github.com/EugenMayer/docker-sync/wiki/docker-sync-on-Windows).

**Linux:**

Instalar [Docker](https://docs.docker.com/engine/installation/linux/docker-ce/ubuntu/) y [Docker-compose](https://docs.docker.com/compose/install/#install-compose).

### Como usar

De forma automática se creará una imagen Wordpress y Wordpress Cli, se instalará WooCommerce con el tema Storefront y se creará un producto de ejemplo.

Para instalar Woocommerce, hacer lo siguiente y esperar 5 minutos:

```
docker compose up
```

Para Eliminar ejecutar y borrar las carpetas 'db_data' y 'wp_data':

```
docker compose down
```

### Paneles

**Web server:** http://localhost:8000

**Admin:** http://localhost:8000/wp-admin

    user: admin
    password: admin
