[![GitHub tag (latest by date)](https://img.shields.io/github/v/tag/transbankdevelopers/transbank-plugin-woocommerce-webpay-rest)](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay-rest/releases/latest)
[![GitHub](https://img.shields.io/github/license/transbankdevelopers/transbank-plugin-woocommerce-webpay-rest)](LICENSE)
[![GitHub contributors](https://img.shields.io/github/contributors/transbankdevelopers/transbank-plugin-woocommerce-webpay-rest)](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay-rest/graphs/contributors)
[![Release](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay-rest/actions/workflows/release.yml/badge.svg)](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay-rest/actions/workflows/release.yml)

# Transbank Woocommerce Webpay Plugin
Plugin oficial de Webpay para WooCommerce
![transbank-woocommerce-plugin](https://user-images.githubusercontent.com/1103494/114062234-4b74d980-9865-11eb-9a59-232be4846365.png)


## Descripción
Este plugin **oficial** de Transbank te permite integrar Webpay fácilmente en tu sitio WooCommerce. Está desarrollado en base al [SDK oficial de PHP](https://github.com/TransbankDevelopers/transbank-sdk-php)

### ¿Cómo instalar?
1. [Descarga la última versión del plugin](https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay-rest/releases/latest)
2. Sube el archivo zip en la sección Plugin > Subir nuevo plugin en el administrador de tu Wordpress

Puedes ver las instrucciones de instalación y su documentación completa en la página de [Transbank Developers](https://www.transbankdevelopers.cl/plugin/woocommerce/)

### Paso a producción
Al instalar el plugin, este vendrá configurado para funcionar en modo **integración** (en el ambiente de pruebas de Transbank). 
Para poder operar con dinero real (ambiente de **producción**), debes completar el proceso de validación simplificado para plugins. Revisa el paso a paso [acá](https://transbankdevelopers.cl/plugin/woocommerce/). 


# Desarrollo
A continuación, encontrarás información necesaria para el desarrollo de este plugin. 

## Requisitos 
* PHP 7.4 o superior
* Woocommerce 7.0 o superior

## Dependencias

El plugin depende de las siguientes librerías:

* transbank/transbank-sdk:~2.0
* monolog/monolog

Para cumplir estas dependencias, debes instalar [Composer](https://getcomposer.org), e instalarlas con el comando `composer install`.

## Nota  
- La versión del sdk de php se encuentra en el archivo [composer.json](plugin/composer.json)

## Desarrollo

Para apoyar el levantamiento rápido de un ambiente de desarrollo, hemos creado la especificación de contenedores a través de Docker Compose.

Para testear los ejemplos estos están disponibles en:
- [WooCommerce 6.0 con php 7.4](./docker-woocommerce-php7.4-wp6.0.0-wc2.6.0-woo6.0.0)
- [WooCommerce 6.5 con php 7.4](./docker-woocommerce-php7.4-wp6.0.0-wc2.6.0-woo6.5.0)
- [WooCommerce 8.1 con php 8.2](./docker-woocommerce-php8.2-wp6.4.1-wc2.9.0-woo8.1.0)
- [WooCommerce 8.2 con php 8.2](./docker-woocommerce-php8.2-wp6.4.1-wc2.9.0-woo8.2.0)
- [WooCommerce 8.3 con php 8.2](./docker-woocommerce-php8.2-wp6.4.1-wc2.9.0-woo8.3.0)
- [WooCommerce 8.5 con php 8.2](./docker-woocommerce-php8.2-wp6.4.1-wc2.9.0-woo8.5.0)

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

1. Modificar el archivo `plugin/readme.txt` para incluir una nueva entrada (al comienzo) para `X.Y.Z` que explique en español los cambios. (bajo el título == Changelog ==)
2. Modificar el archivo `plugin/readme.txt` para incluir una nueva entrada en la sección == Upgrade Notice == con una explicación breve de porque se debe actualizar.

Luego de obtener aprobación del pull request, debes mezclar a master e inmediatamente generar un release en GitHub con el tag `vX.Y.Z`. En la descripción del release debes poner lo mismo que agregaste al changelog.
Con eso Travis CI generará automáticamente una nueva versión del plugin y actualizará el Release de Github con el zip del plugin, además de crear el release en el SVN de Wordpress.org.

## Estándares generales

- Para los commits nos basamos en las siguientes normas: https://github.com/angular/angular.js/blob/master/DEVELOPERS.md#commits👀
- Todas las mezclas a master se hacen mediante Pull Request ⬇️
- Usamos inglés para los mensajes de commit 💬
- Se pueden usar tokens como WIP en el subject de un commit separando el token con ':', por ejemplo -> 'WIP: this is a useful commit message'
- Para los nombres de ramas también usamos inglés
- Se asume que una rama de feature no mezclada, es un feature no terminado ⚠️
- El nombre de las ramas va en minúscula 🔤
- El nombre de la rama se separa con '-' y las ramas comienzan con alguno de los short lead tokens definidos a continuación, por ejemplo -> 'feat/tokens-configuration' 🌿
  
### **Short lead tokens**

`WIP` = En progreso

`feat` = Nuevos features

`fix` = Corrección de un bug

`docs` = Cambios solo de documentación

`style` = Cambios que no afectan el significado del código (espaciado, formateo de código, comillas faltantes, etc)

`refactor` = Un cambio en el código que no arregla un bug ni agrega una funcionalidad

`perf` = Cambio que mejora el rendimiento

`test` = Agregar test faltantes o los corrige

`chore` = Cambios en el build o herramientas auxiliares y librerías


## Reglas

1️⃣ -  Si no se añaden test en el pull request, se debe añadir un video o gif mostrando el cambio realizado y demostrando que la rama no rompe nada.

2️⃣ -  El pr debe tener 2 o mas aprobaciones para hacer el merge

3️⃣ - si un commit revierte  un commit anterior deberá comenzar con "revert:" seguido con texto del commit anterior

## Pull Request

### Asunto ✉️

- Debe comenzar con el short lead token definido para la rama, seguido de ':' y una breve descripción del cambio
- Usar imperativos en tiempo presente: "change" no "changed" ni "changes"
- No usar mayúscula en el inicio
- No usar punto . al final

### Descripción 📃

Igual que en el asunto, usar imperativo y en tiempo presente. Debe incluir una mayor explicación de lo que se hizo en el pull request. Si no se añaden test en el pull request, se debe añadir un video o gif mostrando el cambio realizado y demostrando que la rama no rompe nada.
