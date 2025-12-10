# DevContainer - Woocommerce Webpay Module

Este devcontainer proporciona un entorno completo de desarrollo para el módulo Webpay de Woocommerce.

## 🚀 Inicio rápido

1. Abre el proyecto en VS Code
2. Cuando se te pregunte, selecciona "Reopen in Container"
3. Espera a que se construya el contenedor (puede tomar unos minutos la primera vez)
4. Una vez listo, Woocommerce estará disponible en http://localhost:8000

## 📋 Servicios incluidos
-   **Wordpress 6.8.3** con PHP 8.2.
-   **Woocommerce 10.3.6** con PHP 8.2.
-   **Node 20.X**
-   **Wordpress CLI**
-   **Woocommerce CLI**
-   **Mysql 8** como base de datos.
-   **Apache** para servir el contenido.
-   **Extensiones de VS Code** para trabajar con PHP y Woocommerce
-   **Composer** para gestión de dependencias PHP.

## 🔗 URLs de acceso

| Servicio      | Acceso                          | Credenciales                         |
| ------------- | ------------------------------- | ------------------------------------ |
| Woocommerce   | http://localhost:8000           | -                                    |
| Admin Panel   | http://localhost:8000/wp-admin  | admin / admin                        |
| Base de datos | VS Code SQLTools/MySQL Client   | wordpress / wordpress                |

## 🛠️ Herramientas de desarrollo

### Administración de base de datos con VS Code

El devcontainer incluye una extensión para trabajar con la base de datos:

#### SQLTools

-   **Acceso**: Ctrl/Cmd + Shift + P → "SQLTools: Connect"
-   **Conexiones preconfiguradas**:
    -   `WooCommerce Mysql` - Base de datos principal
    -   `Mysql Root` - Acceso administrativo completo
-   **Funcionalidades**: Explorar tablas, ejecutar queries, exportar datos

### Estructura del proyecto en el contenedor

```
/workspace/                                                 # Código fuente (montado desde el host)
/var/www/html/                                              # Instalación de Wordpress con Woocommerce
/var/www/html/wp-content/plugins/transbank-webpay-plus-rest # Módulo Webpay (enlazado desde /workspace/plugin)
```

## 🔧 Configuración del módulo

El módulo Webpay se monta automáticamente en `/var/www/html/wp-content/plugins/transbank-webpay-plus-rest` y se activa, el sitio instala productos de prueba y configura la tienda.

### Desarrollo del módulo

1. Los cambios se reflejan automáticamente en Wordpress
2. Los logs se guardan en `.devcontainer/logs/`
3. Se ha incluido la carpeta de Wordpress en Intelephense para tener las referencias de código de Wordpress.

## 📦 Dependencias

Las dependencias de Composer se instalan automáticamente al crear el contenedor por primera vez. Si necesitas instalar nuevas dependencias:

```bash
cd /workspace/plugin
composer require nueva-dependencia
```

## 🗄️ Base de datos

### Configuración por defecto

-   Host: `db`
-   Puerto: `3306`
-   Base de datos: `wordpress`
-   Usuario: `wordpress`
-   Contraseña: `wordpress`

## 📝 Notas de desarrollo

1. **Permisos**: El usuario es root, por lo que tiene acceso completo al contenedor.
2. **Persistencia**: Los datos de Wordpress **persisten** entre reinicios.
3. **Hot reload**: Los cambios en PHP se aplican inmediatamente.
4. **Logs**: Los logs se encuentra en .devcontainer/logs

## Edición devcontainer

En caso de editar el devcontainer, es importante que se reconstruya la imagen para que los cambios se reflejen si ya se uso anteriormente.
En algunas ocasiones detecta los cambios y el editor sugiere reconstruir el contenedor. En caso contrario se debe hacer manualmente.

### Reconstruir el devcontainer

-   Desde VS Code: abre la paleta de comandos (Ctrl/Cmd + Shift + P) → ejecuta **Dev Containers: Rebuild Container**. Selecciona **Rebuild Container** para iniciar el proceso.
-   Alternativa rápida: haz clic en el icono de la esquina inferior izquierda (Remote) → "Reopen in Container" y acepta la opción de reconstruir si se muestra.
-   Si no se aplica algún cambio (Docker no disponible o caché): reconstruye manualmente desde tu entorno Docker según tu flujo de trabajo local (ej. build sin caché), o elimina la imagen del devcontainer antes de reconstruir.
-   Nota importante: la reconstrucción vuelve a crear la imagen y el contenedor; cualquier dato no persistente en el contenedor (ej. instalación temporal de PrestaShop) se perderá. Asegúrate de respaldar lo necesario antes de reconstruir.