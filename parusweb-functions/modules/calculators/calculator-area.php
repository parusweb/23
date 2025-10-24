<?php
/**
 * Калькулятор: Площадь (Area Calculator)
 * Описание: Калькулятор для товаров с площадью в названии (пиломатериалы, листовые)
 * Категории: 87-93 (пиломатериалы), 190-191, 127, 94 (листовые)
 * Зависимости: category-helpers, product-calculations
 * 
 * ВАЖНО: Этот файл содержит ТОЛЬКО функцию отображения
 * Подключение через add_action происходит в calculator-display.php
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Функция вывода калькулятора площади
 * Вызывается из calculator-display.php
 */
function display_area_calculator($product_id, $price) {
    $product = wc_get_product($product_id);
    if (!$product) {
        return;
    }
    
    // Получаем данные товара
    $title = $product->get_title();
    $area_data = function_exists('extract_area_with_qty') ? extract_area_with_qty($title, $product_id) : null;
    
    if (!$area_data || $area_data <= 0) {
        return;
    }
    
    $is_leaf = function_exists('is_leaf_category') ? is_leaf_category($product_id) : false;
    $unit_text = $is_leaf ? 'лист' : 'упаковка';
    $unit_text_plural = $is_leaf ? 'листов' : 'упаковок';
    
    ?>
    <div id="area-calculator" class="parusweb-calculator" style="margin: 20px 0; padding: 20px; border: 2px solid #ddd; border-radius: 8px; background: #f9f9f9;">
        <h4 style="margin-top: 0;">Калькулятор по площади</h4>
        
        <div style="margin-bottom: 15px; padding: 12px; background: #e8f4f8; border-radius: 4px;">
            <strong>Площадь одной <?php echo $unit_text; ?>:</strong> <?php echo number_format($area_data, 3, ',', ' '); ?> м²
        </div>
        
        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Количество <?php echo $unit_text_plural; ?>:</label>
            <input type="number" id="area_packs" name="custom_area_packs" 
                   min="1" value="1" step="1"
                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px;">
        </div>
        
        <div id="area_calc_result" style="padding: 15px; background: #fff; border: 2px solid #ddd; border-radius: 6px; margin-top: 15px;">
            <div style="margin-bottom: 10px;">
                <strong>Общая площадь:</strong> <span id="area_total_area"><?php echo number_format($area_data, 2, ',', ' '); ?></span> м²
            </div>
            <div style="font-size: 18px; color: #2271b1; font-weight: 700;">
                <strong>Итого:</strong> <span id="area_total_price"><?php echo wc_price($price * $area_data); ?></span>
            </div>
        </div>
        
        <!-- Скрытые поля для передачи данных -->
        <input type="hidden" id="area_value" name="custom_area_area_value" value="<?php echo esc_attr($area_data); ?>">
        <input type="hidden" id="area_total_price_value" name="custom_area_total_price" value="<?php echo esc_attr($price * $area_data); ?>">
        <input type="hidden" id="area_grand_total_value" name="custom_area_grand_total" value="<?php echo esc_attr($price * $area_data); ?>">
        <input type="hidden" id="area_base_price" value="<?php echo esc_attr($price); ?>">
        <input type="hidden" id="area_pack_area" value="<?php echo esc_attr($area_data); ?>">
        <input type="hidden" id="area_is_leaf" value="<?php echo $is_leaf ? '1' : '0'; ?>">
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        console.log('✓ Area Calculator initialized');
        
        $('#area_packs').on('input change', function() {
            const packs = parseInt($(this).val()) || 1;
            const packArea = parseFloat($('#area_pack_area').val()) || 0;
            const basePrice = parseFloat($('#area_base_price').val()) || 0;
            
            const totalArea = packs * packArea;
            const totalPrice = totalArea * basePrice;
            
            $('#area_total_area').text(totalArea.toFixed(2));
            $('#area_total_price').html('<?php echo get_woocommerce_currency_symbol(); ?>' + Math.round(totalPrice).toLocaleString('ru-RU'));
            
            $('#area_total_price_value').val(totalPrice);
            $('#area_grand_total_value').val(totalPrice);
            
            // Обновляем количество в корзине
            $('input.qty').val(packs).prop('readonly', true).css('background-color', '#f5f5f5');
        });
        
        // Инициализируем расчет
        $('#area_packs').trigger('input');
    });
    </script>
    <?php
}
