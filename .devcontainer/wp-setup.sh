#!/bin/sh
set -e

# --- Environment Variables Setup
WORDPRESS_PATH="${WORDPRESS_PATH:-/var/www/html}"
PLUGIN_SLUG="transbank-webpay-plus-rest"
PLUGIN_DIR="${WORDPRESS_PATH}/wp-content/plugins/${PLUGIN_SLUG}"
PLUGIN_SRC="/workspace/plugin"
HTACCESS="${WORDPRESS_PATH}/.htaccess"

# --- Create a symbolic link from the workspace directory
# --- to the WordPress plugins directory
if [ -d "$PLUGIN_SRC" ]; then
    if [ ! -e "$PLUGIN_DIR" ]; then
        echo "Creando symlink del plugin..."
        ln -s "$PLUGIN_SRC" "$PLUGIN_DIR"
    else
        echo "El destino $PLUGIN_DIR ya existe (directorio o symlink)."
    fi
else
    echo "No existe carpeta de plugin en $PLUGIN_SRC"
fi

# --- Install dependencies for the plugin (composer + npm)
if [ -d "$PLUGIN_DIR" ]; then
  echo "Encontrado plugin en ${PLUGIN_DIR}. Instalando dependencias..."

  cd "$PLUGIN_DIR"

  if [ -f "composer.json" ] && [ ! -d "vendor" ]; then
      echo "Ejecutando composer install..."
      composer install || echo "Composer falló, Continuando..."
  else
    echo "Vendor ya existe, saltando composer install."
  fi

  if [ -f "package.json" ] && [ ! -d "node_modules" ]; then
      echo "Ejecutando npm install y npm run build..."
      npm install --ignore-scripts || echo "npm install falló."
      npm run build || echo "npm run build falló."
  else
    echo "node_modules ya existe, saltando npm run install y npm run build."
  fi

  cd -
else
  echo "No se encontró el plugin en ${PLUGIN_DIR}. No se instalarán dependencias."
fi

# --- Create the .htaccess file in the WordPress directory
if [ ! -f "$HTACCESS" ]; then
  touch "$HTACCESS"
fi

# --- Modify .htaccess and PHP limits
if ! grep -q "upload_max_filesize 5000M" "$HTACCESS"; then
cat <<'EOF' >> "$HTACCESS"
php_value upload_max_filesize 5000M
php_value post_max_size 5000M
php_value memory_limit 256M
php_value max_execution_time 300
php_value max_input_time 300
EOF
fi

# --- Install WordPress if it's not installed
cd "$WORDPRESS_PATH"
if ! wp core is-installed --path="$WORDPRESS_PATH" --allow-root; then
  wp core install \
    --path="$WORDPRESS_PATH" \
    --url="http://localhost:8000" \
    --title="Transbank Store" \
    --admin_user=admin \
    --admin_password=admin \
    --admin_email=transbankdevelopers@continuum.cl \
    --allow-root
fi

# --- Install WooCommerce
if ! wp plugin is-installed woocommerce --allow-root; then
  echo "Instalando WooCommerce..."
  wp plugin install woocommerce --version=10.6.1 --activate --allow-root || true
else
  echo "WooCommerce ya instalado. Actualizando..."
  wp plugin update woocommerce --version=10.6.1 --allow-root || true

  if ! wp plugin is-active woocommerce --allow-root; then
    echo "Activando WooCommerce..."
    wp plugin activate woocommerce --allow-root || true
  else
    echo "WooCommerce ya instalado y activo."
  fi
fi

# --- Install Spectra One theme
if ! wp theme is-installed spectra-one --allow-root; then
  echo "Instalando tema Spectra One..."
  wp theme install spectra-one --activate --allow-root || true
else
  echo "Spectra One ya instalado y activo."
fi


# --- Install WooCommerce pages
wp wc tool run install_pages --user=admin --allow-root || true

# --- Add demo product
EXISTS_DEMO_PRODUCT=$(wp wc product list \
  --user=admin \
  --allow-root \
  --sku=1 \
  --format=count \
  2>/dev/null || echo 0)

if [ "${EXISTS_DEMO_PRODUCT}" -eq 0 ]; then
  echo "Creando producto demo..."
  wp wc product create \
    --name="Zapatos deportivos" \
    --sku=1 \
    --regular_price=1000 \
    --status=publish \
    --user=admin \
    --allow-root
else
  echo "Producto demo con SKU=1 ya existe, no se crea de nuevo."
fi

# --- Configure shop options for WooCommerce
wp option update woocommerce_currency "CLP" --allow-root
wp option update woocommerce_store_address "Nueva Tajamar 481" --allow-root
wp option update woocommerce_store_address_2 "Oficina 1704, Torre Sur" --allow-root
wp option update woocommerce_store_city "Las Condes" --allow-root
wp option update woocommerce_default_country "CL" --allow-root
wp option update woocommerce_store_postcode "7500000" --allow-root
wp option update woocommerce_price_num_decimals "0" --allow-root
wp option update woocommerce_price_thousand_sep "." --allow-root
wp option update woocommerce_price_decimal_sep "," --allow-root
wp option update woocommerce_coming_soon no --allow-root
wp option update woocommerce_feature_site_visibility_badge_enabled no --allow-root
wp option update woocommerce_enable_myaccount_registration yes --allow-root
wp option update woocommerce_enable_signup_and_login_from_checkout yes --allow-root

# --- Set debug options for WooCommerce
wp config set WP_DEBUG true --type=constant --allow-root --raw
wp config set WP_DEBUG_DISPLAY false --type=constant --allow-root --raw --add
wp config set WPS_DEBUG true --type=constant --allow-root --raw --add
wp config set WPS_DEBUG_SCRIPTS true --type=constant --allow-root --raw --add
wp config set WPS_DEBUG_STYLES true --type=constant --allow-root --raw --add
wp config set WP_DEBUG_LOG "/var/log/wordpress/debug.log" --type=constant --allow-root

# --- Activate Transbank plugin
if [ -d "$PLUGIN_DIR" ]; then
  if ! wp plugin is-active transbank-webpay-plus-rest --allow-root; then
    echo "Activando plugin ${PLUGIN_SLUG}..."
    wp plugin activate "${PLUGIN_SLUG}" --allow-root || true
  else
    echo "plugin de Transbank esta activo."
  fi
else
  echo "No se encontró el plugin en ${PLUGIN_DIR} para activarlo."
fi

# --- Activate Webpay Plus
STATUS_WEBPAY=$(wp wc payment_gateway get transbank_webpay_plus_rest --allow-root --user=admin | awk '/enabled/ {print $2}')

if [ "$STATUS_WEBPAY" = "false" ]; then
  echo "Activando opción de Webpay Plus de Transbank plugin"
  wp wc payment_gateway update transbank_webpay_plus_rest --allow-root --user=admin --enabled=true
else
  echo "Ya se encuentra activa la opción de Webpay Plus"
fi

# --- Activate Webpay OneClick
STATUS_ONECLICK=$(wp wc payment_gateway get transbank_oneclick_mall_rest --allow-root --user=admin | awk '/enabled/ {print $2}')

if [ "$STATUS_ONECLICK" = "false" ]; then
  echo "Activando opción de OneClick de Transbank plugin"
  wp wc payment_gateway update transbank_oneclick_mall_rest --allow-root --user=admin --enabled=true
else
  echo "Ya se encuentra activa la opción de OneClick"
fi

echo "Setup de WordPress + WooCommerce + Transbank completado."
