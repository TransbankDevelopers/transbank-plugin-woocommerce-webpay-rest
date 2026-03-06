=== Transbank Webpay ===
Contributors: TransbankDevelopers
Tags: transbank, webpay_plus, webpay_oneclick
Requires at least: 5.3
Tested up to: 6.9.1
Requires PHP: 8.2
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

== Installation ==

= Instalación Automática =

1. Inicia sesión en tu panel de administración de WordPress.
2. Haz clic en __Plugins__.
3. Haz clic en __Añadir nuevo__.
4. Busca __Transbank Webpay__.
5. Haz clic en __Instalar ahora__.
6. Activa el plugin.

= Instalación Manual =

1. Descarga el plugin desde [GitHub](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay-rest/releases/latest).
2. Extrae el contenido del archivo .zip.
3. Sube el contenido extraído a la carpeta `wp-content/plugins/` de tu instalación de WordPress.
4. Activa el plugin Transbank Webpay desde la __página de Plugins__.

== Screenshots ==

1. Página de configuración de Webpay Plus
2. Página de configuración de Webpay Oneclick
3. Página de diagnostico
4. Página de logs

== Changelog ==
= 1.12.2 =

Esta versión no tiene cambios en el comportamiento de las funcionalidades de la API.
Esta versión no tiene cambios de funcionalidades en el plugin, solo cambia el proceso de empaquetado.
¡Importante! La versión mínima soportada de PHP ahora es 8.2

__Actualiza:__

* Se refina el flujo de empaquetado del plugin.

= 1.12.1 =

Esta versión no tiene cambios en el comportamiento de las funcionalidades de la API.
¡Importante! La versión mínima soportada de PHP ahora es 8.2

__Actualiza:__

* Ahora es requisito utilizar PHP 8.2

= 1.12.0 =

Esta versión no tiene cambios en el comportamiento de las funcionalidades de la API.
¡Importante! La versión mínima soportada de PHP ahora es 8.2

__Agrega:__

* Administrador de inscripciones de Oneclick en el panel de administración.

__Actualiza:__

* Textos para los checkbox de activación de productos.
* Dependencias necesarias para construir el plugin
* Refina los flujos de pago para los productos Webpay Plus y Oneclick.
* Refina la interpretación de los campos en la operación de status.
* Rutina para la creación de tablas en la instalación del plugin.
* Clases para la configuración del plugin.
* Gestión de notificaciones del plugin.
* Refina el flujo para eliminar inscripciones.
* Refina el flujo de manejo de logs.
* Ahora es requisito utilizar PHP 8.2

__Elimina:__

* Tablas deprecadas para el registro de eventos.

= 1.11.0 =
Esta versión no tiene cambios en el comportamiento de las funcionalidades de la API.

__Agrega:__

* Se agrega a la opción de utilizar un formato de orden de compra personalizado para las transacciones de Webpay Plus y Webpay Oneclick. Esto se puede realizar desde las opciones de configuración de cada producto.

__Actualiza:__

* Se permite consultar el estado de las transacciones Webpay Plus y Webpay Oneclick para todas las órdenes que tenga una  transacción asociada. Antes solo se permitía si la transacción se encontraba aprobada previamente.
* Se actualiza los nombres de columnas en la tabla transacciones con el objetivo de promover una lectura más clara y coherente.
* Se actualiza el texto de la opción de activación de producto con el objetivo de promover una lectura más clara y coherente.
* Se actualizan las dependencias necesarias para construir el plugin.

= 1.10.0 =
Esta versión no tiene cambios en el comportamiento de las funcionalidades de la API.

__Agrega:__

* Se agrega botón de descarga para los archivos de logs.

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


== Upgrade Notice ==
= 1.12.2 =

Esta versión no tiene cambios en el comportamiento de las funcionalidades de la API.
Esta versión no tiene cambios de funcionalidades en el plugin, solo cambia el proceso de empaquetado.
¡Importante! La versión mínima soportada de PHP ahora es 8.2

__Actualiza:__

* Se refina el flujo de empaquetado del plugin.

= 1.12.1 =

Esta versión no tiene cambios en el comportamiento de las funcionalidades de la API.
¡Importante! La versión mínima soportada de PHP ahora es 8.2

__Actualiza:__

* Ahora es requisito utilizar PHP 8.2

= 1.12.0 =

Esta versión no tiene cambios en el comportamiento de las funcionalidades de la API.
¡Importante! La versión mínima soportada de PHP ahora es 8.2

__Agrega:__

* Administrador de inscripciones de Oneclick en el panel de administración.

__Actualiza:__

* Textos para los checkbox de activación de productos.
* Dependencias necesarias para construir el plugin
* Refina los flujos de pago para los productos Webpay Plus y Oneclick.
* Refina la interpretación de los campos en la operación de status.
* Rutina para la creación de tablas en la instalación del plugin.
* Clases para la configuración del plugin.
* Gestión de notificaciones del plugin.
* Refina el flujo para eliminar inscripciones.
* Refina el flujo de manejo de logs.
* Ahora es requisito utilizar PHP 8.2

__Elimina:__

* Tablas deprecadas para el registro de eventos.

= 1.11.0 =
Esta versión no tiene cambios en el comportamiento de las funcionalidades de la API.

__Agrega:__

* Se agrega a la opción de utilizar un formato de orden de compra personalizado para las transacciones de Webpay Plus y Webpay Oneclick. Esto se puede realizar desde las opciones de configuración de cada producto.

__Actualiza:__

* Se permite consultar el estado de las transacciones Webpay Plus y Webpay Oneclick para todas las órdenes que tenga una  transacción asociada. Antes solo se permitía si la transacción se encontraba aprobada previamente.
* Se actualiza los nombres de columnas en la tabla transacciones con el objetivo de promover una lectura más clara y coherente.
* Se actualiza el texto de la opción de activación de producto con el objetivo de promover una lectura más clara y coherente.
* Se actualizan las dependencias necesarias para construir el plugin.

= 1.10.0 =
Esta versión no tiene cambios en el comportamiento de las funcionalidades de la API.

__Agrega:__

* Se agrega botón de descarga para los archivos de logs.

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

