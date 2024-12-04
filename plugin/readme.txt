=== Transbank Webpay ===
Contributors: TransbankDevelopers
Tags: transbank, webpay plus, webpay oneclick
Requires at least: 5.3
Tested up to: 6.7.1
Requires PHP: 7.4
Stable tag: VERSION_REPLACE_HERE
License: 3-Clause BSD License
License URI: https://opensource.org/licenses/BSD-3-Clause

Recibe pagos en línea con tarjetas de crédito, débito y prepago en tu WooCommerce a través de Webpay Plus y Webpay Oneclick.

== Description ==
🚀 ¡Haz crecer tu negocio con nuestro plugin oficial de Transbank para WooCommerce!

Permite a tus clientes realizar pagos en línea de forma rápida, segura y conveniente gracias a la integración con Webpay Plus y Webpay Oneclick, las soluciones líderes de pago en Chile. Con este módulo, ofrecerás una experiencia de compra fluida y confiable, fortaleciendo la confianza de tus clientes y aumentando tus conversiones. 💳✨

### Beneficios:
- 🔒 **Pagos 100% seguros**: Cumple con los más altos estándares de seguridad para proteger a tus clientes.
- ⚡ **Experiencia sin fricciones**: Con Webpay Oneclick, permite que los clientes habituales paguen con un solo clic.
- 🛠️ **Fácil integración**: Configuración rápida y sencilla directamente desde WooCommerce.
- ✅ **Compatibilidad garantizada**: Funciona con las últimas versiones de WooCommerce y WordPress.

Transforma tu eCommerce con el plugin oficial de Transbank y dale a tus clientes la confianza que necesitan para comprar una y otra vez.

**¿Necesitas más información?:**

* [Documentación del Plugin](https://www.transbankdevelopers.cl/plugin/woocommerce/)
* [Documentación Webpay Plus](https://www.transbankdevelopers.cl/documentacion/webpay-plus)
* [Documentación Webpay Oneclick](https://www.transbankdevelopers.cl/documentacion/oneclick)
* [Comunidad de Slack](https://transbank.continuumhq.dev/slack_community)
* [Repositorio de GitHub](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay-rest)

🌟 ¿Te gusta nuestro plugin? ¿Lo encuentras útil? Por favor considera compartir tu experiencia dejando una referencia en [WordPress.org](). Tu feedback es valioso para continuar mejorando.
== Screenshots ==

1. Página de configuración de Webpay Plus
2. Página de configuración de Webpay Oneclick
3. Página de diagnostico

== Changelog ==
= 1.10.0 =
Esta versión no tiene cambios en el comportamiento de las funcionalidades de la API.

__Actualiza:__

* Se actualizan las dependencias para ampliar la compatibilidad con plugin de terceros.
* Se actualiza el diseño de la respuesta de estado de transacción.

= 1.9.3 =
Esta versión no tiene cambios en el comportamiento de las funcionalidades de la API.

* Se refina el flujo de pago de Oneclick.

= 1.9.2 =
Esta versión no tiene cambios en el comportamiento de las funcionalidades de la API.

* Se refina el flujo de pago de Webpay y Oneclick.

= 1.9.1 =
* Se arregla un mensaje de warning provocado por la función maskData en PHP mayor o igual a 8.x.
* Se arregla un problema que impedía encontrar el archivo de log al migrar el sitio de un servidor a otro.
* Se arregla la zona horaria de los logs. Se usa la que está configurada en el ecommerce del comercio.
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
* Se arregla la zona horaria de los logs. Se usa la que está configurada en el ecommerce del comercio.
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
