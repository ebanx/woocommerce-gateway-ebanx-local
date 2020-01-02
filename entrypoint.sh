#!/bin/sh

set -e

export WP_ROOT=/var/www/html

/usr/local/bin/wait-for-it.sh -t 60 mysql:3306 -- echo 'MySQL is up!'

if [ "$WOOCOMMERCE_EXTERNAL_PORT" != "80" ]; then
    WOOCOMMERCE_URL="${WOOCOMMERCE_URL}:${WOOCOMMERCE_EXTERNAL_PORT}"
fi

if ! $(wp core is-installed --allow-root); then
  cd $WP_ROOT

  if [ ! -e .htaccess ]; then
    cat > .htaccess <<-'EOF'
				# BEGIN WordPress
				<IfModule mod_rewrite.c>
				RewriteEngine On
				RewriteBase /
				RewriteRule ^index\.php$ - [L]
				RewriteCond %{REQUEST_FILENAME} !-f
				RewriteCond %{REQUEST_FILENAME} !-d
				RewriteRule . /index.php [L]
				</IfModule>
				# END WordPress
		EOF
  fi

  chown www-data:www-data $WP_ROOT

  wp core install --url=$WOOCOMMERCE_URL --title="$EBANX_SITE_TITLE" --admin_user=$EBANX_ADMIN_USERNAME --admin_password=$EBANX_ADMIN_PASSWORD --admin_email=$EBANX_SITE_EMAIL --skip-email --allow-root
  wp config set WP_DEBUG false --raw --allow-root

  # Install and activate storefrontheme
  wp theme install storefront --version=$EBANX_STOREFRONT_THEME_VERSION --activate --allow-root

  # Install and activate plugins
  wp plugin install woocommerce --version=$WOOCOMMERCE_PLUGIN_VERSION --activate --allow-root
  wp plugin activate woocommerce-gateway-ebanx-local --allow-root

  # Install Pages
  wp post create --post_type=page --post_title='My Account' --post_status='publish' --post_content='[woocommerce_my_account]' --allow-root
  wp post create --post_type=page --post_title='Cart' --post_status='publish' --post_content='[woocommerce_cart]' --allow-root
  wp post create --post_type=page --post_title='Checkout' --post_status='publish' --post_content='[woocommerce_checkout]' --allow-root
  wp post create --post_type=page --post_title='Shop' --post_status='publish' --allow-root

  # Configure WooCommerce settings
  wp db query 'UPDATE wp_options SET option_value="BR" WHERE option_name="woocommerce_default_country"' --allow-root
  wp db query 'UPDATE wp_options SET option_value="BRL" WHERE option_name="woocommerce_currency"' --allow-root
  wp db query 'UPDATE wp_options SET option_value="4" WHERE option_name="woocommerce_myaccount_page_id"' --allow-root
  wp db query 'UPDATE wp_options SET option_value="5" WHERE option_name="woocommerce_cart_page_id"' --allow-root
  wp db query 'UPDATE wp_options SET option_value="6" WHERE option_name="woocommerce_checkout_page_id"' --allow-root
  wp db query 'UPDATE wp_options SET option_value="7" WHERE option_name="woocommerce_shop_page_id"' --allow-root
  wp db query 'UPDATE wp_options SET option_value="a:57:{s:19:\"sandbox_private_key\";s:30:\"test_ik_XRsjfeba9c8ibhVv10NUiw\";s:18:\"sandbox_public_key\";s:30:\"test_pk_ORbz0mIejNrVMrxvlUKhUQ\";s:20:\"sandbox_mode_enabled\";s:3:\"yes\";s:13:\"debug_enabled\";s:2:\"no\";s:22:\"brazil_payment_methods\";a:2:{i:0;s:20:\"ebanx-credit-card-br\";i:1;s:20:\"ebanx-banking-ticket\";}s:14:\"save_card_data\";s:3:\"yes\";s:9:\"one_click\";s:3:\"yes\";s:15:\"capture_enabled\";s:3:\"yes\";s:26:\"br_credit_card_instalments\";s:1:\"6\";s:13:\"due_date_days\";s:1:\"3\";s:20:\"brazil_taxes_options\";a:1:{i:0;s:3:\"cpf\";}s:25:\"br_interest_rates_enabled\";s:2:\"no\";s:17:\"show_local_amount\";s:3:\"yes\";s:27:\"br_min_instalment_value_brl\";s:1:\"5\";s:16:\"live_private_key\";s:0:\"\";s:15:\"live_public_key\";s:0:\"\";s:20:\"br_interest_rates_01\";s:0:\"\";s:20:\"br_interest_rates_02\";s:0:\"\";s:20:\"br_interest_rates_03\";s:0:\"\";s:20:\"br_interest_rates_04\";s:0:\"\";s:20:\"br_interest_rates_05\";s:0:\"\";s:20:\"br_interest_rates_06\";s:0:\"\";s:20:\"br_interest_rates_07\";s:0:\"\";s:20:\"br_interest_rates_08\";s:0:\"\";s:20:\"br_interest_rates_09\";s:0:\"\";s:20:\"br_interest_rates_10\";s:0:\"\";s:20:\"br_interest_rates_11\";s:0:\"\";s:20:\"br_interest_rates_12\";s:0:\"\";s:20:\"br_interest_rates_13\";s:0:\"\";s:20:\"br_interest_rates_14\";s:0:\"\";s:20:\"br_interest_rates_15\";s:0:\"\";s:20:\"br_interest_rates_16\";s:0:\"\";s:20:\"br_interest_rates_17\";s:0:\"\";s:20:\"br_interest_rates_18\";s:0:\"\";s:20:\"br_interest_rates_19\";s:0:\"\";s:20:\"br_interest_rates_20\";s:0:\"\";s:20:\"br_interest_rates_21\";s:0:\"\";s:20:\"br_interest_rates_22\";s:0:\"\";s:20:\"br_interest_rates_23\";s:0:\"\";s:20:\"br_interest_rates_24\";s:0:\"\";s:20:\"br_interest_rates_25\";s:0:\"\";s:20:\"br_interest_rates_26\";s:0:\"\";s:20:\"br_interest_rates_27\";s:0:\"\";s:20:\"br_interest_rates_28\";s:0:\"\";s:20:\"br_interest_rates_29\";s:0:\"\";s:20:\"br_interest_rates_30\";s:0:\"\";s:20:\"br_interest_rates_31\";s:0:\"\";s:20:\"br_interest_rates_32\";s:0:\"\";s:20:\"br_interest_rates_33\";s:0:\"\";s:20:\"br_interest_rates_34\";s:0:\"\";s:20:\"br_interest_rates_35\";s:0:\"\";s:20:\"br_interest_rates_36\";s:0:\"\";s:24:\"checkout_manager_enabled\";s:2:\"no\";s:35:\"checkout_manager_brazil_person_type\";s:0:\"\";s:27:\"checkout_manager_cpf_brazil\";s:0:\"\";s:28:\"checkout_manager_cnpj_brazil\";s:0:\"\";s:21:\"manual_review_enabled\";s:2:\"no\";}" WHERE option_name="woocommerce_ebanx-global_settings"' --allow-root

  # Create a product
  wp wc product create --name='Jeans' --status='publish' --regular_price='250' --user=1 --allow-root

  # Configure Permalink
  wp rewrite structure '/%postname%/' --hard --allow-root

  echo "EBANX: Visit http://$WOOCOMMERCE_URL/shop/ or http://$WOOCOMMERCE_URL/wp-admin/"
  echo "EBANX: Username - $EBANX_ADMIN_USERNAME"
  echo "EBANX: Password - $EBANX_ADMIN_PASSWORD"
fi
apache2-foreground
