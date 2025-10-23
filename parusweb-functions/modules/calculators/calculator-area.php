<?php
/**
 * Калькулятор: Площадь (Area Calculator)
 * Описание: Калькулятор для товаров с площадью в названии (пиломатериалы, листовые)
 * Категории: 87-93 (пиломатериалы), 190-191, 127, 94 (листовые)
 * Зависимости: category-helpers, product-calculations
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Вывод калькулятора площади на странице товара
 */
add_action('woocommerce_before_add_to_cart_button', 'display_area_calculator', 5);
function display_area_calculator() {
    global $product;
    
    if (!$product) {
        return;
    }
    
    $product_id = $product->get_id();
    
    // Проверяем нужен ли калькулятор площади
    if (!function_exists('get_calculator_type') || get_calculator_type($product_id) !== 'area') {
        return;
    }
    
    // Получаем данные товара
    $title = $product->get_title();
    $base_price = $product->get_price();
    $area_data = function_exists('extract_area_with_qty') ? extract_area_with_qty($title, $product_id) : null;
    
    if (!$area_data || $area_data <= 0) {
        return;
    }
    
    $is_leaf = function_exists('is_leaf_category') ? is_leaf_category($product_id) : false;
    $unit_text = $is_leaf ? 'лист' : 'упаковка';
    $unit_text_plural = $is_leaf ? 'листов' : 'упаковок';
    
    ?>
    <div id="area-calculator" class="parusweb-calculator" style="margin: 20px 0; padding: 20px; border: 2px solid #4CAF50; border-radius: 8px; background: #f9f9f9;">
        <h4 style="margin-top: 0; color: #2c5f2d;">Калькулятор по площади</h4>
        
        <div style="margin-bottom: 15px; padding: 12px; background: #e8f4f8; border-radius: 4px; border-left: 4px solid #4CAF50;">
            <strong>Площадь одной <?php echo $unit_text; ?>:</strong> <?php echo number_format($area_data, 3, ',', ' '); ?> м²
        </div>
        
        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Количество <?php echo $unit_text_plural; ?>:</label>
            <input type="number" id="area_packs" name="custom_area_packs" 
                   min="1" value="1" step="1"
                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px;">
        </div>
        
        <div id="area_calc_result" style="padding: 15px; background: #fff; border: 2px solid #4CAF50; border-radius: 6px; margin-top: 15px;">
            <div style="margin-bottom: 10px;">
                <strong>Общая площадь:</strong> <span id="area_total_area"><?php echo number_format($area_data, 2, ',', ' '); ?></span> м²
            </div>
            <div style="font-size: 20px; color: #4CAF50; font-weight: 700;">
                <strong>Итого:</strong> <span id="area_total_price"><?php echo wc_price($base_price * $area_data); ?></span>
            </div>
        </div>
        
        <!-- Скрытые поля для передачи данных -->
        <input type="hidden" id="area_value" name="custom_area_area_value" value="<?php echo esc_attr($area_data); ?>">
        <input type="hidden" id="area_total_price_value" name="custom_area_total_price" value="<?php echo esc_attr($base_price * $area_data); ?>">
        <input type="hidden" id="area_grand_total_value" name="custom_area_grand_total" value="<?php echo esc_attr($base_price * $area_data); ?>">
        <input type="hidden" id="area_base_price" value="<?php echo esc_attr($base_price); ?>">
        <input type="hidden" id="area_pack_area" value="<?php echo esc_attr($area_data); ?>">
        <input type="hidden" id="area_is_leaf" value="<?php echo $is_leaf ? '1' : '0'; ?>">
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        'use strict';
        
        // Функция обновления расчетов
        function updateAreaCalculator() {
            const packs = parseInt($('#area_packs').val()) || 1;
            const packArea = parseFloat($('#area_pack_area').val()) || 0;
            const basePrice = parseFloat($('#area_base_price').val()) || 0;
            
            if (packs < 1 || packArea <= 0 || basePrice <= 0) {
                return;
            }
            
            const totalArea = packs * packArea;
            const totalPrice = packs * (packArea * basePrice);
            
            // Добавляем стоимость покраски если выбрана
            let grandTotal = totalPrice;
            const paintingSelect = $('#painting_service_select');
            if (paintingSelect.length && paintingSelect.val()) {
                const paintingPricePerM2 = parseFloat(paintingSelect.find('option:selected').data('price')) || 0;
                if (paintingPricePerM2 > 0) {
                    grandTotal += totalArea * paintingPricePerM2;
                }
            }
            
            // Обновляем отображение
            $('#area_total_area').text(totalArea.toFixed(2).replace('.', ','));
            $('#area_total_price').text(formatPrice(grandTotal));
            
            // Обновляем скрытые поля
            $('#area_value').val(totalArea);
            $('#area_total_price_value').val(totalPrice);
            $('#area_grand_total_value').val(grandTotal);
            
            // Обновляем количество WooCommerce
            $('input.qty').val(packs);
        }
        
        // Форматирование цены
        function formatPrice(price) {
            return Math.round(price).toLocaleString('ru-RU') + ' ₽';
        }
        
        // Обработчик изменения количества
        $('#area_packs').on('input change', updateAreaCalculator);
        
        // Обработчик изменения покраски
        $(document).on('change', '#painting_service_select, #painting_color_select', updateAreaCalculator);
        
        // Инициализация
        updateAreaCalculator();
        
        // Блокируем стандартное поле количества
        $('input.qty').prop('readonly', true).css('background-color', '#f5f5f5');
        
        console.log('✓ Area Calculator initialized');
    });
    </script>
    <?php
}