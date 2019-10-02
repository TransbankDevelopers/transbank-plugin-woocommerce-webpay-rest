composer install
wp --allow-root --allow-root core install --url=localhost:8082 --title=transbank --admin_user=admin --admin_password=admin --admin_email=transbankdevelopers@continuum.cl
wp --allow-root plugin install woocommerce --version=3.4.0
wp --allow-root plugin activate woocommerce
#wp --allow-root plugin activate woocommerce-transbank

wp --allow-root wc tool run install_pages --user=admin
wp --allow-root wc product create --name="Zapatos deportivos" --sku=1 --regular_price=1000 --status=publish --user=admin
wp --allow-root theme install storefront
wp --allow-root theme activate storefront
#wp --allow-root wc payment_gateway update woocommerce-transbank --enabled=true  --user=admin
wp --allow-root db query "UPDATE wp_options SET option_value='CLP' WHERE option_name='woocommerce_currency';"
wp --allow-root db query "UPDATE wp_options SET option_value='General Bustamante 24' WHERE option_name='woocommerce_store_address';"
wp --allow-root db query "UPDATE wp_options SET option_value='Of M, Piso 7' WHERE option_name='woocommerce_store_address_2';"
wp --allow-root db query "UPDATE wp_options SET option_value='Providencia' WHERE option_name='woocommerce_store_city';"
wp --allow-root db query "UPDATE wp_options SET option_value='CL' WHERE option_name='woocommerce_default_country';"
wp --allow-root db query "UPDATE wp_options SET option_value='7500000' WHERE option_name='woocommerce_store_postcode';"

wp --allow-root db query "UPDATE wp_options SET option_value=0 WHERE option_name='woocommerce_price_num_decimals';"
wp --allow-root db query "UPDATE wp_options SET option_value='.' WHERE option_name='woocommerce_price_thousand_sep';"
wp --allow-root db query "UPDATE wp_options SET option_value=',' WHERE option_name='woocommerce_price_decimal_sep';"

wp --allow-root config set WP_DEBUG true
wp --allow-root config set --add --type=constant WP_DEBUG_LOG true
wp --allow-root config set --add --type=constant WP_DEBUG_DISPLAY false
wp --allow-root config set --add --type=constant WPS_DEBUG true
wp --allow-root config set --add --type=constant WPS_DEBUG_SCRIPTS true
wp --allow-root config set --add --type=constant WPS_DEBUG_STYLES true
