# Transbank Woocommerce Webpay

Plugin oficial de Woocommerce para Webpay

## Descripción

Este plugin de Woocommerce implementa el [SDK PHP de Webpay](https://github.com/TransbankDevelopers/transbank-sdk-php).

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
- La versión del sdk de php se encuentra en el archivo `woocommerce-transbank/composer.json`

## Desarrollo

Para apoyar el levantamiento rápido de un ambiente de desarrollo, hemos creado la especificación de contenedores a través de Docker Compose.

Para usarlo seguir el siguiente [README Woocommerce 3.4.0 con php 5.6](./docker-php5.6)  

### Actualizar versión del SDK de Transbank
Para actualizar la versión del SDK de Transbank se debe editar el archivo `woocommerce-transbank/composer.json` y cambiar
el valor de la propiedad `"transbank/transbank-sdk"` por la versión que se desea instalar y luego ejecutar el bash `update`
que esta en la carpeta `docker-woocommerce-php5.6` lo que actualizara la dependencia del plugin.

### Crear el instalador del plugin

    ./package.sh

## Generar una nueva versión

Para generar una nueva versión, se debe crear un PR (con un título "Prepare release X.Y.Z" con los valores que correspondan para `X`, `Y` y `Z`). Se debe seguir el estándar semver para determinar si se incrementa el valor de `X` (si hay cambios no retrocompatibles), `Y` (para mejoras retrocompatibles) o `Z` (si sólo hubo correcciones a bugs).

En ese PR deben incluirse los siguientes cambios:

1. Modificar el archivo CHANGELOG.md para incluir una nueva entrada (al comienzo) para `X.Y.Z` que explique en español los cambios.

Luego de obtener aprobación del pull request, debes mezclar a master e inmediatamente generar un release en GitHub con el tag `vX.Y.Z`. En la descripción del release debes poner lo mismo que agregaste al changelog.

Con eso Travis CI generará automáticamente una nueva versión del plugin y actualizará el Release de Github con el zip del plugin.
