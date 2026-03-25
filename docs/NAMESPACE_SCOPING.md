## Renombrado de namespaces durante el build y empaquetado

Este proyecto renombra namespaces de dependencias de terceros durante el proceso de build y empaquetado para reducir problemas de compatibilidad con otros plugins de WordPress instalados en el mismo sitio.

El artefacto final incluye las dependencias externas prefijadas bajo el namespace `TransbankVendor`. Esto evita colisiones en runtime con librerías compartidas como el SDK de Transbank, Monolog, interfaces PSR y Guzzle.

### Como funciona el proceso

1. `./package.sh` crea un directorio temporal de build e instala las dependencias runtime del plugin.
2. Los assets frontend se generan dentro de ese directorio temporal.
3. `php-scoper` se ejecuta sobre el directorio `vendor/` del plugin y escribe el resultado en `vendor-prefixed/`.
4. Luego el build aplica dos pasos de normalización:
   - `scripts/fix_scoper_autoload.sh` ajusta los mapas de autoload de Composer generados para las dependencias prefijadas.
   - `scripts/apply_scope_replacements.sh` actualiza las referencias en el código del plugin que deben apuntar a los namespaces prefijados.
5. El directorio `vendor/` sin prefijar se elimina del artefacto final.
6. `plugin/webpay-rest.php` carga el autoloader prefijado cuando existe `vendor-prefixed/` y, en caso contrario, hace fallback al autoloader estándar de `vendor/`.

### Por que existe duplicacion intencional

`plugin/scoper-namespaces.php` contiene tres mapas relacionados:

- `runtime_psr4`
- `autoload_replacements`
- `code_replacement_patterns`

Esto es intencional. Aunque algunos mapeos se ven similares, cada uno resuelve una necesidad distinta dentro del flujo de build/runtime:

- `runtime_psr4` se usa en el bootstrap del plugin para resolver clases prefijadas en runtime.
- `autoload_replacements` se usa para corregir los archivos de autoload generados por Composer dentro de `vendor-prefixed/composer/`.
- `code_replacement_patterns` se usa para reescribir referencias PHP dentro del código del plugin que deben apuntar a los namespaces prefijados.

Como estas etapas operan sobre artefactos distintos, esta duplicación se mantiene de forma explícita por diseño. La prioridad es favorecer un flujo de empaquetado predecible y fácil de depurar antes que una abstracción más implícita.

### Consideraciones al cambiar dependencias

- Si una nueva librería de terceros debe ser prefijada, actualiza `plugin/scoper-namespaces.php`.
- Si esa librería es referenciada directamente desde el código del plugin, asegúrate de cubrir esas referencias en los patrones de reemplazo.
- Después de cambiar reglas de scope o dependencias, regenera el paquete y valida el plugin usando el artefacto empaquetado, no solo el árbol fuente.

### Que editar al agregar una nueva dependencia

Si se agrega una nueva dependencia de terceros y debe formar parte del proceso de namespace scoping, se debe revisar al menos lo siguiente:

- `plugin/composer.json`: para declarar la nueva dependencia runtime del plugin.
- `plugin/scoper-namespaces.php`: para agregar el namespace prefijado en los mapas `runtime_psr4`, `autoload_replacements` y, si aplica, `code_replacement_patterns`.
- Código del plugin en `plugin/src`, `plugin/shared` o `plugin/views`: si la dependencia es usada directamente mediante imports, referencias estáticas o strings de clase, validar que esas referencias queden cubiertas por el proceso de reemplazo.
- `plugin/webpay-rest.php`: normalmente no requiere cambios adicionales si `runtime_psr4` fue actualizado correctamente, pero debe verificarse cuando la estructura PSR-4 de la nueva librería no siga el patrón esperado.

Después de eso, se debe generar nuevamente el paquete con `./package.sh` y validar el plugin usando el artefacto empaquetado final.
