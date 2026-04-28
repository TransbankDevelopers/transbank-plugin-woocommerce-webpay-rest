## Renombrado de namespaces durante el build y empaquetado

Este proyecto renombra namespaces de dependencias de terceros durante el proceso de build y empaquetado para reducir problemas de compatibilidad con otros plugins de WordPress instalados en el mismo sitio.

El artefacto final incluye las dependencias externas prefijadas bajo el namespace `TransbankVendor`. Esto evita colisiones en runtime con librerías compartidas como el SDK de Transbank, Monolog, interfaces PSR y Guzzle.

### Como funciona el proceso

1. `./package.sh` copia el contenido de `plugin/` a un directorio temporal de trabajo en `build/package-plugin/`.
2. Dentro de ese directorio temporal ejecuta `composer install --no-dev --prefer-dist`, `npm install --no-audit --no-fund --no-optional` y `npm run build`.
3. Después del build elimina `node_modules/` y `assets/src/` del artefacto temporal para no incluir archivos de desarrollo.
4. Si `ENABLE_SCOPER=1`, `php-scoper` se ejecuta con `plugin/scoper.inc.php`, toma `vendor/` como entrada y escribe el resultado en `vendor-prefixed/`.
5. Luego el build aplica dos pasos de normalización:
   - `scripts/fix_scoper_autoload.sh` ajusta los mapas de autoload de Composer generados para las dependencias prefijadas.
   - `scripts/apply_scope_replacements.sh` actualiza las referencias en el código del plugin que deben apuntar a los namespaces prefijados.
6. El directorio `vendor/` sin prefijar se elimina del artefacto final.
7. Antes de generar el zip, `./package.sh` valida que:
   - exista `vendor-prefixed/autoload.php`
   - no exista `vendor/`
   - los mapas `vendor-prefixed/composer/autoload_psr4.php` y `vendor-prefixed/composer/autoload_static.php` contengan los prefijos esperados
   - los namespaces propios del plugin no hayan sido prefijados por error
   - `webpay-rest.php` siga teniendo sintaxis PHP valida
8. El zip final se genera desde el contenido ya procesado de `build/package-plugin/`.

### Carga en runtime

`plugin/webpay-rest.php` delega la carga de dependencias en `plugin/load-autoloader.php`.

- Si existe `vendor-prefixed/autoload.php`, el plugin carga opcionalmente `vendor-prefixed/scoper-autoload.php` y luego el autoloader prefijado.
- Si no existe `vendor-prefixed/autoload.php`, hace fallback a `vendor/autoload.php`.

### Por que existe duplicacion intencional

`plugin/scoper-namespaces.php` contiene tres mapas relacionados:

- `runtime_psr4`
- `autoload_replacements`
- `code_replacement_patterns`

No todos cumplen el mismo rol:

- `autoload_replacements` se usa para corregir los archivos de autoload generados por Composer dentro de `vendor-prefixed/composer/`.
- `code_replacement_patterns` se usa para reescribir referencias PHP dentro del código del plugin que deben apuntar a los namespaces prefijados.
- `runtime_psr4` existe como mapa declarativo de namespaces prefijados y rutas PSR-4, pero actualmente no es consumido directamente por `./package.sh`, `plugin/load-autoloader.php` ni los scripts de normalización.

La parte operativa del empaquetado depende hoy de `autoload_replacements` y `code_replacement_patterns`. Si `runtime_psr4` se mantiene, conviene tratarlo como documentación viva de los namespaces prefijados esperados y revisar periódicamente si sigue aportando valor.

### Consideraciones al cambiar dependencias

- Si una nueva librería de terceros debe ser prefijada, actualiza `plugin/scoper-namespaces.php`.
- Si esa librería es referenciada directamente desde el código del plugin, asegúrate de cubrir esas referencias en los patrones de reemplazo.
- Revisa también si `package.sh` necesita nuevas validaciones explícitas para esa librería en los mapas de autoload.
- Después de cambiar reglas de scope o dependencias, regenera el paquete y valida el plugin usando el artefacto empaquetado, no solo el árbol fuente.

### Que editar al agregar una nueva dependencia

Si se agrega una nueva dependencia de terceros y debe formar parte del proceso de namespace scoping, se debe revisar al menos lo siguiente:

- `plugin/composer.json`: para declarar la nueva dependencia runtime del plugin.
- `plugin/scoper-namespaces.php`: para agregar el namespace prefijado en `autoload_replacements` y, si aplica, en `code_replacement_patterns`. Si se mantiene `runtime_psr4`, actualizarlo tambien para que no quede desalineado.
- Código del plugin en `plugin/src`, `plugin/shared` o `plugin/views`: si la dependencia es usada directamente mediante imports, referencias estáticas o strings de clase, validar que esas referencias queden cubiertas por el proceso de reemplazo.
- `package.sh`: si corresponde, para agregar o ajustar validaciones sobre prefijos obligatorios o namespaces no permitidos.
- `plugin/load-autoloader.php`: normalmente no requiere cambios mientras el autoload generado por Composer siga resolviendo correctamente `vendor-prefixed/`.

Después de eso, se debe generar nuevamente el paquete con `./package.sh` y validar el plugin usando el artefacto empaquetado final.
