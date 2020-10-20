# Changelog
Todos los cambios notables a este proyecto serán documentados en este archivo.

El formato está basado en [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
y este proyecto adhiere a [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [2.7.8] - 2020-09-28
### Fixed
- Se soluciona forma en que se verifica si la orden puede ser pagada, ahora utiliza los métodos correspondientes en el ciclo de vida de una orden

## [2.7.7] - 2020-08-13
### Fixed
- Se soluciona error que provocaba que no se creara la tabla webpay_transactions en algunas versiones de mysql [#PR 143](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay/pull/143)

## [2.7.6] - 2020-07-27
### Fixed
- Se soluciona error que provocaba que la API REST de Woocommerce y otros procesos asíncronos fallaran [#PR 141](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay/pull/141)

## [2.7.5] - 2020-07-02
### Fixed
- Se actualiza SDK de PHP para solucionar problemas de compatibilidad con OpenSSL 1.1

## [2.7.4] - 2020-06-17
### Fixed
- Se arregla error en PHP <= 5.6 por uso de reserved keyword 'print' en el nombre de un método. [#PR 131](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay/pull/131) 

## [2.7.3] - 2020-06-10
### Fixed
- Se arregla error que provoca excepción si una orden se paga con otro medio de pago [Issuee #127](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay/pull/127)
- Se arregla error generado al iniciar las sesiones de PHP si ya se habían enviado headers  [Issuee #127](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay/pull/127)
### Added
- Se agrega posibilidad de traducir mensajes dentro del plugin [Issuee #127](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay/pull/128)


## [2.7.2] - 2020-06-09
### Fixed
- Cuando el usuario anula la compra en el formulario de webpay, ya no se borra el carrito de compras y la orden queda en estado 'Cancelled' en vez de 'Failed' [PR #124](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay/pull/124) [Issue #120](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay/issues/116) y [Issue #116](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay/issues/120)
- En algunos casos de borde, la orden que era pagada correctamente, seguidamente se marcaba como pendiente de pago. Eso ya no pasa. [PR #124](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay/pull/124) [Issue #122](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay/issues/122)
- Ahora la tabla webpay_transactions ahora se crea correctamente en MySQL 5.5 [PR #124](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay/pull/124)

## [2.7.1] - 2020-04-27
### Fixed
- Arregla error en caso de borde cuando el cliente vuelve a la URL de response después de haber pagado correctamente [PR #117](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay/pull/117)
- Mejora en algunos textos [PR #118](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay/pull/118)
- Arregla error en path de asset en el admin [PR #102](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay/pull/102)

## [2.7.0] - 2020-04-16
### Feature
- Cambia las sessiones por una tabla en la base de datos [PR #113](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay/pull/113)
### Fixed
- Arregla el tamaño de la imagen de pago [PR #108](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay/pull/108)

## [2.6.1] - 2020-03-19
### Fixed
- Arregla posible error al llamar a API de telemetría [PR #105](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay/pull/105)

## [2.6.0] - 2020-03-16
### Changed
- README estandarizado, con links a repositorios con ejemplos utilizando distintas versiones de woocommerce + PHP, en [PR #98](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay/pull/98).
- Se añade soporte comprobado al plugin hasta woocommerce 4.0.0 en [PR #100](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay/pull/100).

## [2.5.3] - 2020-03-03
### Fixed
- Actualiza documentación del Readme [PR #88](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay/pull/88), [PR #89](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay/pull/89), [PR #91](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay/pull/91) 
- Se actualiza token encriptado de Github para Travis [PR #90](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay/pull/90) 
- Se modifica estado por defecto al finalizar una orden para que ahora sea 'wc-processing' y no 'wc-pending' en [PR #96](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay/pull/96) de [@TCattd](https://github.com/TCattd)
- Se resuelve [Issue #92](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay/issues/92) enviado por [@svaldesm](https://github.com/svaldesm) en [PR #95](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay/pull/95)

## [2.5.2] - 2020-02-17
### Fixed
- Agrega soporte a PHP 7.4
- Se cambia logo de webpay en página de pago
- Elimina `require` innecesario que generaba incompatibilidades con otros plugins
- Disminuye el tamaño del archivo empaquetado de 17.3MB a 3.9MB

## [2.5.1] - 2020-01-28
### Fixed
- Mejora la seguridad de la exportación de reporte
- Mejora detalles gráficos en la pantalla de configuración, como menciona @Kyberal en [PR #69](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay/pull/69)
- Agrega soporte a PHP 7.3
- Agrega tcpdf como dependencia de composer

## [2.5.0] - 2020-01-07
### Added
- Agrega métricas de uso cuando se pasa a producción en la configuración o al actualizar el plugin.

## [2.4.1] - 2019-11-18
### Fixed
- Corrige confirmación del pago de la orden de compra, permite a los integradores asociar flujos a woocommerce_payment_complete.

## [2.4.0] - 2019-10-02
### Changed
- Añade selección de estado por defecto de las compras una vez que se realiza el pago, en la pantalla de configuración del plugin

## [2.3.1] - 2019-06-26
### Fixed
- Corrige problema que cancela órdenes en estado procesadas. Verifica que las orden se encuentre en estado pendiente antes de cambiar el estado.

## [2.3.0] - 2019-06-10
### Changed
- Se añade soporte comprobado al plugin hasta php 7.2.19 + woocommerce 3.6.3 + wordpress 5.2.1.

## [2.2.5] - 2019-05-29
### Fixed
- Corrige problema con la verificación de la conexión con Transbank en la configuración del plugin, ahora la verificación despliega el resultado correctamente.
- Agrega fecha y hora a los campos personalizados del pedido.

## [2.2.4] - 2019-05-06
### Fixed
- Corrige error al activar el plugin cuando es instalado con un nombre diferente. Se buscan los archivos con una ruta dinámica en base a la ruta del plugin.

## [2.2.3] - 2019-04-30
### Fixed
- Corrige problema que cambia el estado de las órdenes de procesadas a fallidas. Verifica que las orden se encuentre en estado pendiente antes de cambiar el estado.

## [2.2.2] - 2019-04-04
### Fixed
- Corrige configuración, Ya no es necesario incluir el certificado de Webpay
- Corrige despliegue de información en el detalle de la transacción realizada, ahora se visualiza toda la información

## [2.2.1] - 2019-03-13
### Fixed
- Corrige función que controla la reducción de stock (se estaba utilizando una función actualmente deprecada).

## [2.2.0] - 2019-02-18
### Fixed
- Corrige problema que impide ejecutar el plugin en Integración, cuando está recién instalado.
- Indica a WooCommerce que el plugin es compatible con la versión 3.5.4
### Changed
- Al recibir el pago de forma exitósa, el estado de la compra pasa a "Processing" en vez de "Completed".

## [2.1.5] - 2019-01-10
### Changed
- Se elimina la condición de VCI == "TSY" || VCI == "" para evaluar la respuesta de getTransactionResult debido a que
esto podría traer problemas con transacciones usando tarjetas internacionales.

## [2.1.4] - 2018-12-27
### Added
- Agrega logs de transacciones para poder obtener los datos como token, orden de compra, etc.. necesarios para el proceso de certificación.

## [2.1.3] - 2018-12-18
### Fixed
- Corrige el sistema de configuraciones del plugin.
### Added
- Agrega funcionalidad para probar el servicio Webpay desde el panel de configuraciones del plugin.
- Mejoras en el proceso de pago y creación de la orden en estados correctos.

## [2.1.2] - 2018-12-18
### Fixed
- Se corrige un error de la sección de administración al guardar las configuraciones que provocaba error con la validación de certificados.

## [2.1.1] - 2018-12-17
### Fixed
- Se corrige un error de la sección de administración al verificar los certificados.

## [2.1.0] - 2018-12-14
### Changed
- Ahora soporta php 7.1
### Fixed
- Se mejoran el proceso de pago con Webpay.
- Se corrigen varios errores de la sección de administración del plugin.
