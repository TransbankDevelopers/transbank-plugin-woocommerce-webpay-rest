# Changelog
Todos los cambios notables a este proyecto serán documentados en este archivo.

El formato está basado en [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
y este proyecto adhiere a [Semantic Versioning](http://semver.org/spec/v2.0.0.html).


## [1.1.0] - 2020-11-09
### Added 
- Mejora compatibilidad con Wordpress MU [PR #9](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay-rest/pull/9)
- Añade mensaje cuando Woocommerce no está configurado en Pesos chilenos [PR #15](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay-rest/pull/15)

### Fixed
- Se arregla documentación de instalación [PR #8](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay-rest/pull/8)
- Eliminar REST del nombre del plugin para el cliente final [PR #14](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay-rest/pull/14)
- Arregla error en la creción de la tabla `webpay_rest_transactions` [PR #16](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay-rest/pull/16)
- Soluciona error en comprobante de pago donde no se mostraba el número de cuotas [PR #18](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay-rest/pull/18)

## [1.0.1] - 2020-11-04
### Fixed
- Se soluciona error que ocasiaonada que al pasar a producción se siguiera utilizando el ambiente de prueba [PR #6](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay-rest/pull/6)

## [1.0.0] - 2020-11-02
### Added
- Release inicial
