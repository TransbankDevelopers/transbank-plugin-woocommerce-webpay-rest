[![GitHub tag (latest by date)](https://img.shields.io/github/v/tag/transbankdevelopers/transbank-plugin-woocommerce-webpay)](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay/releases/latest)
[![GitHub](https://img.shields.io/github/license/transbankdevelopers/transbank-plugin-woocommerce-webpay)](LICENSE)
[![GitHub contributors](https://img.shields.io/github/contributors/transbankdevelopers/transbank-plugin-woocommerce-webpay)](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay/graphs/contributors)
[![Build Status](https://travis-ci.org/TransbankDevelopers/transbank-plugin-woocommerce-webpay.svg?branch=master)](https://travis-ci.org/TransbankDevelopers/transbank-plugin-woocommerce-webpay)

# Transbank Woocommerce Webpay Plugin

Plugin oficial de Webpay para WooCommerce

## Descripción

Este plugin **oficial** de Transbank te permite integrar Webpay fácilmente en tu sitio WooCommerce. Está desarrollado en base al [SDK oficial de PHP](https://github.com/TransbankDevelopers/transbank-sdk-php)

### ¿Cómo instalar?
Puedes ver las instrucciones de instalación y su documentación completa en la página de [Transbank Developers](https://www.transbankdevelopers.cl/plugin/woocommerce/)


### Paso a producción
Al instalar el plugin, este vendrá configurado para funcionar en modo **integración** (en el ambiente de pruebas de Transbank). Para poder operar con dinero real (ambiente de **producción**), debes:

1. Tener tu propio código de comercio. Si no lo tienes, solicita Webpay Plus en [transbank.cl](https://publico.transbank.cl)
2. Luego de finalizar tu integración debes [generar tus credenciales](https://www.transbankdevelopers.cl/documentacion/como_empezar#credenciales-en-webpay)  (llave privada y llave pública) usando tu código de comercio. 
3. Enviar [esta planilla de integración](https://transbankdevelopers.cl/files/evidencia-integracion-webpay-plugins.docx) a soporte@transbank.cl, junto con la llave pública (generada en el paso anterior) y tu **logo (130x59 pixeles en formato GIF)**. 
4. Cuando Transbank confirme que ha cargado tu certificado público y logo, debes entrar a la pantalla de configuración del plugin dentro de WooCommerce y colocar tu código de comercio, llave privada, llave pública y poner el ambiente de 'Producción'. 
5. Debes hacer una compra de $10 en el ambiente de producción para confirmar el correcto funcionamiento. 

Puedes ver más información sobre este proceso en [este link](https://www.transbankdevelopers.cl/documentacion/como_empezar#puesta-en-produccion).

# Desarrollo
A continuación, encontrarás información necesaria para el desarrollo de este plugin. 

## Requisitos 
* PHP 5.6 o superior
* Woocommerce 3.4 o superior

## Dependencias

El plugin depende de las siguientes librerías:

* transbank/transbank-sdk
* tecnickcom/tcpdf
* apache/log4php

Para cumplir estas dependencias, debes instalar [Composer](https://getcomposer.org), e instalarlas con el comando `composer install`.

## Nota  
- La versión del sdk de php se encuentra en el archivo [composer.json](plugin/composer.json)

## Desarrollo

Para apoyar el levantamiento rápido de un ambiente de desarrollo, hemos creado la especificación de contenedores a través de Docker Compose.

Para testear los ejemplos estos estan disponibles en:
- [WooCommerce 3.4.0 con php 5.6](./docker-woocommerce-php5.6)
- [WooCommerce 3.4.0 con php 7.1](./docker-woocommerce-php7.1)
- [WooCommerce 3.6.3 con php 7.2](./docker-woocommerce-php7.2)
- [WooCommerce 3.9.1 con php 7.3](./docker-woocommerce-php7.3)
- [WooCommerce 3.9.1 con php 7.4](./docker-woocommerce-php7.4)

Si necesitas subir el plugin a Woocommerce y obtienes un error por que no se puede mover el archivo a `wp-contentent` entonces ejecuta

```bash
docker-compose run webserver chmod -Rv 767 wp-content/
```

### Actualizar versión del SDK de Transbank
Para actualizar la versión del SDK de Transbank se debe editar el archivo [composer.json](plugin/composer.json) y cambiar
el valor de la propiedad `"transbank/transbank-sdk"` por la versión que se desea instalar y luego ejecutar el bash `update`
que esta en la carpeta `docker-woocommerce-php*` lo que actualizara la dependencia del plugin.

### Crear el instalador del plugin

    ./package.sh

## Generar una nueva versión

Para generar una nueva versión, se debe crear un PR (con un título "Prepare release X.Y.Z" con los valores que correspondan para `X`, `Y` y `Z`). Se debe seguir el estándar semver para determinar si se incrementa el valor de `X` (si hay cambios no retrocompatibles), `Y` (para mejoras retrocompatibles) o `Z` (si sólo hubo correcciones a bugs).

En ese PR deben incluirse los siguientes cambios:

1. Modificar el archivo `CHANGELOG.md` para incluir una nueva entrada (al comienzo) para `X.Y.Z` que explique en español los cambios.

Luego de obtener aprobación del pull request, debes mezclar a master e inmediatamente generar un release en GitHub con el tag `vX.Y.Z`. En la descripción del release debes poner lo mismo que agregaste al changelog.

Con eso Travis CI generará automáticamente una nueva versión del plugin y actualizará el Release de Github con el zip del plugin.
