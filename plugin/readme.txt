=== Transbank Webpay REST ===
Contributors: TransbankDevelopers
Tags: transbank, webpay, oneclick, webpay plus, rest, chile
Requires at least: 5.3
Tested up to: 6.6.2
Requires PHP: 7.4
Stable tag: VERSION_REPLACE_HERE
License: 3-Clause BSD License
License URI: https://opensource.org/licenses/BSD-3-Clause

Recibe pagos en línea con tarjetas de crédito, débito y prepago en tu WooCommerce a través de Webpay Plus y Webpay Oneclick.

== Description ==
Recibe pagos en línea con tarjetas de crédito, débito y prepago en tu WooCommerce a través de Webpay Plus y Webpay Oneclick

== Changelog ==
= 1.9.3 =
Esta versión no tiene cambios en el comportamiento de las funcionalidades de la API.

* Se refina el flujo de pago de Oneclick.

= 1.9.2 =
Esta versión no tiene cambios en el comportamiento de las funcionalidades de la API.

* Se refina el flujo de pago de Webpay y Oneclick.

= 1.9.1 =
* Se arregla un mensaje de warning provocado por la función maskData en PHP mayor o igual a 8.x.
* Se arregla un problema que impedía encontrar el archivo de log al migrar el sitio de un servidor a otro.
* Se arregla la zona horaria de los logs. Se usa la que este configurada en el ecommerce del comercio.
* Se arregla un problema que provocaba registros duplicados en el selector de archivos logs de la vista registros.

= 1.9.0 =
* Se agrega la opción de poder seleccionar el archivo log en la sección de registros del menú de configuración.
* Se agrega la funcionalidad para que se muestren las tarjetas registradas de Oneclick dependiendo del entorno.
* Se agrega el sufijo [Test] a las tarjetas registradas con Oneclick en entorno de integración.
* Se agrega como dependencia el plugin de WooCommerce.
* Se actualiza el título del producto Webpay pasando de Transbank Webpay Plus a Webpay Plus.
* Se arregla un problema que impedía capturar las excepciones cuando se autorizaba una suscripción.
* Se elimina un hook deprecado que provocaba errores de instalación en algunos entornos.

= 1.8.0 =
* Se corrige un problema con el contenido del archivo log que provocaba que se desborde.
* Se agrega la funcionalidad para cambiar la descripción de los medios de pago desde la configuración del plugin.
* Se cambia el capo API Key para que sea de tipo password.
* Se agrega un notice invitando a los usuarios a dejar su review del plugin.

= 1.7.1 =
* Se corrige el formato de la nota de reembolso de pedidos.
* Se corrige un bug en la generación de la orden de compra.

= 1.7.0 =
* Se corrige el funcionamiento de los webhooks implementados para desarrollo de terceros.
* Se ofuscan datos sensibles en el log cuando el entorno es producción.
* Se implementan mejoras en el manejo de logs.
* Se agrega compatibilidad con el checkout por bloques.
* Se agrega compatibilidad con HPOS.
* Se remueve el servicio para recolectar datos del plugin.
* Se corrige la consulta de status a través de Oneclick.
* se registran estados de operaciones en base de datos.
* Se agregan columnas de error en la vista de transacciones.
* Se agregan mejoras en seguridad.
* Corrección de bugs menores.

= 1.6.8 =
* Se remueve la librería de PDF 'tecnickcom/tcpdf' para mejorar compatibilidad.

= 1.6.7 =
* Se sanitiza consulta sql por seguridad.

= 1.6.6 =
* Se corrige un problema con el versionamiento.

= 1.6.5 =
* Se agrega un servicio para recolectar datos que nos permitirán darle mayor seguimiento a las versiones del plugin y las versiones de WooCommerce mas usadas.

= 1.6.4 =
* Se mejora el log detallado para darle seguimiento a los errores.

= 1.6.3 =
* Se mueve la carpeta de logs al interior de la carpeta del plugin

= 1.6.2 =
* Se agrega el uso del comando 'wp_mkdir_p' en la creación de la carpeta usada para guardar logs

= 1.6.1 =
* Se corrige error con librería "monolog/monolog"

= 1.6.0 =
* Se cambia la librería de logs "apache/log4php" por "monolog/monolog": "^1.27" por problemas de compatibilidad

= 1.5.5 =
* Se agrega la posibilidad de verificar si las tablas del plugin fueron creadas (si no existen se crean).
* Se agrega una validación que confirma la inserción en la tabla de transacciones del plugin antes de seguir proceso de pago.
* Se agrega una validación que confirma la inserción en la tabla de inscripción del plugin antes de seguir proceso de inscripción en Oneclick.

= 1.5.4 =
* Se agrega la posibilidad de seleccionar el estado de la orden despues de un pago exitoso para Webpay Plus y Webpay Oneclick

= 1.5.3 =
* Se arregla información de pago al pagar con prepago
* Se arregla caso en que transacción fallida queda anotada como exitosa

= 1.5.2 =
* Se agregan nuevos hooks para que los desarrolladores puedan mejorar su sitio e integrar mejores procesos. Algunos son: transbank_webpay_plus_transaction_failed, transbank_webpay_plus_transaction_approved, transbank_oneclick_refund_approved, transbank_oneclick_transaction_approved, transbank_oneclick_transaction_failed, transbank_oneclick_inscription_finished, transbank_oneclick_inscription_completed, transbank_oneclick_inscription_failed

= 1.5.1 =
* Se arregla warning por llamada a función que no existe en página de pago

= 1.5.0 =
* Se añade soporte para Oneclick Mall REST
* Soporte para Refunds en Oneclick Mall
* Soporte para suscripciones con OneClick Mall REST (WooCommerce Subscriptions)
* Soporte para agregar múltiples tarjetas en cada usuario
* La redirección de Webpay Plus ahora pasa directamente desde el checkout al formulario de pago (sin pasar por una pantalla intermedia como antes)
* El resultado de los reembolsos ahora tiene mejor formato en las de notas del pedido.

= 1.4.1 =
* Ahora el API 1.2 de Transbank a veces redirige por GET al finalizar el flujo y el plugin no funcionaba bien cuando esto pasaba. Ya está arreglado.

= 1.4.0 =
* Se utiliza el nuevo SDk de PHP versión 2.0
* Ya no es compatible con PHP 5.6.
* Ahora es compatible de PHP 7.0 a PHP 8.0
* Ahora se puede completar el formulario de validación directamente desde el plugin
* Se soluciona warning de jQuery [PR 57](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay-rest/pull/57)
* Se aplica coding style de StyleCI.


= 1.3.4 =
* Se mejora compatibilidad con PHP 7.0
* El plugin ya no debería fallar si no existe la extensión ext-soap de PHP

= 1.3.3 =
* Se actualiza el logo de webpay en el formulario de pago
* Se arregla error de tipeo en la página de éxito
* Se añade soporte a Wordpress 5.6
* Se mejora el detalle de las notas del pedido en transacciones aprobadas y rechazadas.


= 1.3.2 =
* Ahora la módulo de "verificar conexión" funciona correctamente cuando el plugin está configurado en modo Producción.

= 1.3.1 =
* Se cambia la posición del menú "Webpay Plus" que antes estaba en el menú principal y ahora bajo el menú WooCommerce

= 1.3.0 =
Agregado:
* Se reemplaza el modal de diagnóstico por pantallas especiales
* Se añade menú 'Webpay plus' en el menú lateral de la administración de Wordpress
* Se añade mensaje de bienvenida al instalar el plugin
* Se mejora compatibilidad con otros plugins
* Se actualiza SDK de PHP a la versión 1.10.0
* Se elimina Boostrap para los estilos de la administración

Arreglado:
* Se arreglan "issues" internas destacadas por el equipo de Wordpress para subir el plugin al repositorio de wordpress.org

= 1.2.0 =
Agregado:
* Ahora se puede consultar el estado de una transacción hecha con webpay plus dentro del detalle de una orden [PR #21](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay-rest/pull/21)
* Se incluye funcionalidad para realizar anulaciones de un pago dentro del detalle de una orden [PR #20](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay-rest/pull/20)
* Mejora página de configuración con mejores textos de ayuda [PR #22](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay-rest/pull/22)

Arreglado:
* Soluciona PDF que no se exportaba [PR #22](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay-rest/pull/22)

= 1.1.0 =
Agregado:
* Mejora compatibilidad con Wordpress MU [PR #9](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay-rest/pull/9)
* Añade mensaje cuando Woocommerce no está configurado en Pesos chilenos [PR #15](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay-rest/pull/15)


= 1.0.1 =
Arreglado:
* Se soluciona error que ocasionaba que al pasar a producción se siguiera utilizando el ambiente de prueba [PR #6](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay-rest/pull/6)

= 1.0.0 =
* Initial release.

== Upgrade Notice ==
= 1.9.3 =
Esta versión no tiene cambios en el comportamiento de las funcionalidades de la API.

* Se refina el flujo de pago de Oneclick.

= 1.9.2 =
Esta versión no tiene cambios en el comportamiento de las funcionalidades de la API.

* Se refina el flujo de pago de Webpay y Oneclick.

= 1.9.1 =
* Se arregla un mensaje de warning provocado por la función maskData en PHP mayor o igual a 8.x.
* Se arregla un problema que impedía encontrar el archivo de log al migrar el sitio de un servidor a otro.
* Se arregla la zona horaria de los logs. Se usa la que este configurada en el ecommerce del comercio.
* Se arregla un problema que provocaba registros duplicados en el selector de archivos logs de la vista registros.

= 1.9.0 =
* Se agrega la opción de poder seleccionar el archivo log en la sección de registros del menú de configuración.
* Se agrega la funcionalidad para que se muestren las tarjetas registradas de Oneclick dependiendo del entorno.
* Se agrega el sufijo [Test] a las tarjetas registradas con Oneclick en entorno de integración.
* Se agrega como dependencia el plugin de WooCommerce.
* Se actualiza el título del producto Webpay pasando de Transbank Webpay Plus a Webpay Plus.
* Se arregla un problema que impedía capturar las excepciones cuando se autorizaba una suscripción.
* Se elimina un hook deprecado que provocaba errores de instalación en algunos entornos.

= 1.8.0 =
* Se corrige un problema con el contenido del archivo log que provocaba que se desborde.
* Se agrega la funcionalidad para cambiar la descripción de los medios de pago desde la configuración del plugin.
* Se cambia el capo API Key para que sea de tipo password.
* Se agrega un notice invitando a los usuarios a dejar su review del plugin.

= 1.7.1 =
* Se corrige el formato de la nota de reembolso de pedidos.
* Se corrige un bug en la generación de la orden de compra.

= 1.7.0 =
* Se corrige el funcionamiento de los webhooks implementados para desarrollo de terceros.
* Se ofuscan datos sensibles en el log cuando el entorno es producción.
* Se implementan mejoras en el manejo de logs.
* Se agrega compatibilidad con el checkout por bloques.
* Se agrega compatibilidad con HPOS.
* Se remueve el servicio para recolectar datos del plugin.
* Se corrige la consulta de status a través de Oneclick.
* se registran estados de operaciones en base de datos.
* Se agregan columnas de error en la vista de transacciones.
* Se agregan mejoras en seguridad.
* Corrección de bugs menores.

= 1.6.8 =
* Se remueve la librería de PDF 'tecnickcom/tcpdf' para mejorar compatibilidad.

= 1.6.7 =
* Se sanitiza consulta sql por seguridad.

= 1.6.6 =
* Se corrige un problema con el versionamiento.

= 1.6.5 =
* Se agrega un servicio para recolectar datos que nos permitirán darle mayor seguimiento a las versiones del plugin y las versiones de WooCommerce mas usadas.

= 1.6.4 =
* Se mejora el log detallado para darle seguimiento a los errores.

= 1.6.3 =
* Se mueve la carpeta de logs al interior de la carpeta del plugin

= 1.6.2 =
* Se agrega el uso del comando 'wp_mkdir_p' en la creación de la carpeta usada para guardar logs

= 1.6.1 =
* Se corrige error con librería "monolog/monolog"

= 1.6.0 =
* Se cambia la librería de logs "apache/log4php" por "monolog/monolog": "^1.27" por problemas de compatibilidad

= 1.5.5 =
* Se agrega la posibilidad de verificar si las tablas del plugin fueron creadas (si no existen se crean).
* Se agrega una validación que confirma la inserción en la tabla de transacciones del plugin antes de seguir proceso de pago.
* Se agrega una validación que confirma la inserción en la tabla de inscripción del plugin antes de seguir proceso de inscripción en Oneclick.

= 1.5.4
* Se agrega la posibilidad de seleccionar el estado de la orden después de un pago exitoso para Webpay Plus y Webpay Oneclick

= 1.5.3
* Se corrige reconocimiento de tipo de pago y si la transacción fue exitosa

= 1.4.1 =
* Se utiliza el nuevo SDk de PHP versión 2.0
* Ya no es compatible con PHP 5.6.
* Ahora es compatible de PHP 7.0 a PHP 8.0
* Ahora se puede completar el formulario de validación directamente desde el plugin
* Se soluciona warning de jQuery [PR 57](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay-rest/pull/57)
* Se aplica coding style de StyleCI.
