<?php
/**
 * Модуль: Выбор объема ЛКМ (Лакокрасочные материалы)
 * Описание: Селектор объема тары для красок с ценой за литр
 * Зависимости: нет
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Доступные объемы тары по брендам
 */
function get_tara_by_brand() {
    return [
        'reiner'    => [1, 3, 5, 25],
        'renowood'  => [1, 3, 5, 9],
        'talatu'    => [1, 3, 5, 18],
        'tikkurila' => [1, 3, 5, 9, 18],
        'woodsol'   => [1, 3, 5, 18]
    ];
}

/**
 * Получение бренда товара
 */
function get_product_brand_for_tara($product_id) {
    // Список возможных таксономий для брендов
    $brand_taxonomies = [
        'product_brand',        // Официальный плагин WooCommerce Brands
        'yith_product_brand',   // YITH WooCommerce Brands
        'pa_brand',            // Атрибут товара "Бренд"
        'pa_brend',            // Атрибут товара "Бренд" (с опечаткой)
        'pwb-brand',           // Perfect WooCommerce Brands
        'brand'                // Другие плагины
    ];
    
    foreach ($brand_taxonomies as $taxonomy) {
        if (taxonomy_exists($taxonomy)) {
            $terms = wp_get_post_terms($product_id, $taxonomy);
            if (!empty($terms) && !is_wp_error($terms)) {
                return strtolower($terms[0]->slug);
            }
        }
    }
    
    // Проверяем мета-поля как запасной вариант
    $meta_keys = ['_brand', 'brand', '_product_brand'];
    foreach ($meta_keys as $key) {
        $brand = get_post_meta($product_id, $key, true);
        if (!empty($brand)) {
            return strtolower(sanitize_title($brand));
        }
    }
    
    return false;
}

/**
 * Вывод селектора объема на странице товара
 */
add_action('woocommerce_before_add_to_cart_button', 'display_lkm_volume_selector', 5);
function display_lkm_volume_selector() {
    global $product;
    
    if (!$product->is_type('simple')) {
        return;
    }
    
    $product_id = $product->get_id();
    
    // Получаем бренд товара
    $brand_slug = get_product_brand_for_tara($product_id);
    
    if (!$brand_slug) {
        return;
    }
    
    $tara_map = get_tara_by_brand();
    
    // Проверяем, есть ли этот бренд в нашем списке объёмов
    if (!empty($tara_map[$brand_slug])) {
        $base_price = wc_get_price_to_display($product);
        ?>
        <div id="lkm-volume-selector" style="margin: 20px 0; padding: 20px; border: 2px solid #00BCD4; border-radius: 8px; background: #f9f9f9;">
            <h4 style="margin-top: 0; color: #00695c;">Выбор объема</h4>
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Объем (л):</label>
                <select id="tara" name="tara" data-base-price="<?php echo esc_attr($base_price); ?>" 
                        style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px;">
                    <?php foreach ($tara_map[$brand_slug] as $volume): ?>
                        <option value="<?php echo esc_attr($volume); ?>"><?php echo esc_html($volume); ?> л</option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div id="lkm_info" style="padding: 15px; background: #e0f7fa; border-radius: 6px; margin-top: 15px;">
                <div style="margin-bottom: 10px;">
                    <strong>Цена за литр:</strong> <span id="lkm_price_per_liter"><?php echo wc_price($base_price); ?></span>
                </div>
                <div id="lkm_discount" style="display: none; color: #4CAF50; font-weight: bold; margin-bottom: 10px;">
                    Скидка 10% при объеме от 9 литров!
                </div>
                <div style="font-size: 20px; color: #00BCD4; font-weight: 700;">
                    <strong>Итого:</strong> <span id="lkm_total_price"><?php echo wc_price($base_price); ?></span>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            'use strict';
            
            const select = $('#tara');
            const basePrice = parseFloat(select.data('base-price'));
            
            function updateLKMPrice() {
                const volume = parseFloat(select.val()) || 1;
                let price = basePrice * volume;
                
                // Скидка 10% при объеме >= 9 литров
                if (volume >= 9) {
                    price *= 0.9;
                    $('#lkm_discount').show();
                } else {
                    $('#lkm_discount').hide();
                }
                
                // Обновляем отображение
                $('#lkm_price_per_liter').html(formatPrice(basePrice));
                $('#lkm_total_price').html(formatPrice(price));
            }
            
            function formatPrice(price) {
                return Math.round(price).toLocaleString('ru-RU') + ' ₽';
            }
            
            select.on('change', updateLKMPrice);
            updateLKMPrice();
            
            console.log('✓ LKM Volume Selector initialized');
        });
        </script>
        <?php
    }
}

/**
 * Добавление объёма в корзину
 */
add_filter('woocommerce_add_cart_item_data', 'add_lkm_volume_to_cart', 10, 3);
function add_lkm_volume_to_cart($cart_item_data, $product_id, $variation_id) {
    if (isset($_POST['tara'])) {
        $cart_item_data['tara'] = floatval($_POST['tara']);
    }
    return $cart_item_data;
}

/**
 * Отображение выбранного объёма в корзине
 */
add_filter('woocommerce_get_item_data', 'display_lkm_volume_in_cart', 10, 2);
function display_lkm_volume_in_cart($item_data, $cart_item) {
    if (!empty($cart_item['tara'])) {
        $item_data[] = [
            'name'  => 'Объем',
            'value' => $cart_item['tara'] . ' л',
        ];
    }
    return $item_data;
}

/**
 * Пересчёт цены в корзине
 */
add_action('woocommerce_before_calculate_totals', 'recalculate_lkm_price_in_cart', 10, 1);
function recalculate_lkm_price_in_cart($cart) {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }

    foreach ($cart->get_cart() as $cart_item) {
        if (!empty($cart_item['tara'])) {
            $price_per_liter = floatval($cart_item['data']->get_regular_price());
            $volume = $cart_item['tara'];
            $final_price = $price_per_liter * $volume;
            
            // Скидка 10% при объеме >= 9 литров
            if ($volume >= 9) {
                $final_price *= 0.9;
            }
            
            $cart_item['data']->set_price($final_price);
        }
    }
}

/**
 * Добавление "за литр" к отображению цены на карточке товара
 */
add_filter('woocommerce_get_price_html', 'add_per_liter_text_to_price', 10, 2);
function add_per_liter_text_to_price($price, $product) {
    if (!is_product()) {
        return $price;
    }
    
    $product_id = $product->get_id();
    $brand_slug = get_product_brand_for_tara($product_id);
    
    if (!$brand_slug) {
        return $price;
    }
    
    $tara_map = get_tara_by_brand();
    
    // Если товар имеет бренд с тарой - добавляем "за литр"
    if (!empty($tara_map[$brand_slug])) {
        // Ищем цену в HTML и добавляем текст
        if (preg_match('/<bdi>(.*?)<\/bdi>/s', $price, $matches)) {
            $price = str_replace($matches[0], $matches[0] . ' <span style="font-size:0.9em;">за литр</span>', $price);
        } else {
            $price .= ' <span style="font-size:0.9em;">за литр</span>';
        }
    }
    
    return $price;
}
