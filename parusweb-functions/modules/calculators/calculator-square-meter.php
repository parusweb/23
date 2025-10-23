<?php
/**
 * Калькулятор: Квадратные метры (Square Meter Calculator)
 * Описание: Калькулятор для товаров продаваемых на квадратные метры
 * Категории: 266, 268, 270 (столярные изделия)
 * Зависимости: category-helpers, product-calculations
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Вывод калькулятора квадратных метров на странице товара
 */
add_action('woocommerce_before_add_to_cart_button', 'display_square_meter_calculator', 5);
function display_square_meter_calculator() {
    global $product;
    
    if (!$product) {
        return;
    }
    
    $product_id = $product->get_id();
    
    // Проверяем нужен ли калькулятор квадратных метров
    if (!function_exists('get_calculator_type') || get_calculator_type($product_id) !== 'square_meter') {
        return;
    }
    
    // Получаем данные товара
    $base_price = $product->get_price();
    $title = $product->get_title();
    $area_data = function_exists('extract_area_with_qty') ? extract_area_with_qty($title, $product_id) : null;
    
    ?>
    <div id="square-meter-calculator" class="parusweb-calculator" style="margin: 20px 0; padding: 20px; border: 2px solid #9C27B0; border-radius: 8px; background: #f9f9f9;">
        <h4 style="margin-top: 0; color: #6a1b9a;">Калькулятор по площади</h4>
        
        <?php if ($area_data && $area_data > 0): ?>
        <div style="margin-bottom: 15px; padding: 12px; background: #f3e5f5; border-radius: 4px; border-left: 4px solid #9C27B0;">
            <strong>Площадь в упаковке:</strong> <?php echo number_format($area_data, 2, ',', ' '); ?> м²
        </div>
        <?php endif; ?>
        
        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Ширина (м):</label>
            <input type="number" id="sq_width" name="custom_sq_width" 
                   min="0.01" step="0.01" value="" placeholder="0.00"
                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px;">
        </div>
        
        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Длина (м):</label>
            <input type="number" id="sq_length" name="custom_sq_length" 
                   min="0.01" step="0.01" value="" placeholder="0.00"
                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px;">
        </div>
        
        <div id="sq_calc_result" style="padding: 15px; background: #fff; border: 2px solid #9C27B0; border-radius: 6px; display: none; margin-top: 15px;">
            <div style="margin-bottom: 10px;">
                <strong>Площадь:</strong> <span id="sq_total_area">0</span> м²
            </div>
            <?php if ($area_data && $area_data > 0): ?>
            <div style="margin-bottom: 10px;">
                <strong>Количество упаковок:</strong> <span id="sq_packs_needed">0</span>
            </div>
            <?php endif; ?>
            <div style="font-size: 20px; color: #9C27B0; font-weight: 700;">
                <strong>Итого:</strong> <span id="sq_total_price">0 ₽</span>
            </div>
        </div>
        
        <!-- Скрытые поля -->
        <input type="hidden" id="sq_price" name="custom_sq_price" value="0">
        <input type="hidden" id="sq_grand_total" name="custom_sq_grand_total" value="0">
        <input type="hidden" id="sq_quantity" name="custom_sq_quantity" value="1">
        <input type="hidden" id="sq_area_per_item" name="custom_sq_area_per_item" value="0">
        <input type="hidden" id="sq_total_area_val" name="custom_sq_total_area" value="0">
        <input type="hidden" id="sq_base_price" value="<?php echo esc_attr($base_price); ?>">
        <input type="hidden" id="sq_pack_area" value="<?php echo esc_attr($area_data ? $area_data : 0); ?>">
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        'use strict';
        
        // Функция обновления расчетов
        function updateSquareMeterCalculator() {
            const width = parseFloat($('#sq_width').val()) || 0;
            const length = parseFloat($('#sq_length').val()) || 0;
            const basePrice = parseFloat($('#sq_base_price').val()) || 0;
            const packArea = parseFloat($('#sq_pack_area').val()) || 0;
            
            if (width <= 0 || length <= 0 || basePrice <= 0) {
                $('#sq_calc_result').hide();
                return;
            }
            
            // Расчет площади
            const area = width * length;
            let totalPrice = area * basePrice;
            let packs = 1;
            
            // Если товар продается упаковками - считаем количество упаковок
            if (packArea > 0) {
                packs = Math.ceil(area / packArea);
                totalPrice = packs * packArea * basePrice;
            }
            
            // Добавляем стоимость покраски если выбрана
            let grandTotal = totalPrice;
            const paintingSelect = $('#painting_service_select');
            if (paintingSelect.length && paintingSelect.val()) {
                const paintingPricePerM2 = parseFloat(paintingSelect.find('option:selected').data('price')) || 0;
                if (paintingPricePerM2 > 0) {
                    grandTotal += area * paintingPricePerM2;
                }
            }
            
            // Обновляем отображение
            $('#sq_total_area').text(area.toFixed(2).replace('.', ','));
            if (packArea > 0) {
                $('#sq_packs_needed').text(packs);
            }
            $('#sq_total_price').text(formatPrice(grandTotal));
            
            // Обновляем скрытые поля
            $('#sq_price').val(totalPrice);
            $('#sq_grand_total').val(grandTotal);
            $('#sq_quantity').val(packs);
            $('#sq_area_per_item').val(area);
            $('#sq_total_area_val').val(area);
            
            // Показываем результат
            $('#sq_calc_result').show();
            
            // Обновляем количество WooCommerce
            $('input.qty').val(packs);
        }
        
        // Форматирование цены
        function formatPrice(price) {
            return Math.round(price).toLocaleString('ru-RU') + ' ₽';
        }
        
        // Обработчики изменений
        $('#sq_width, #sq_length').on('input change', updateSquareMeterCalculator);
        $(document).on('change', '#painting_service_select, #painting_color_select', updateSquareMeterCalculator);
        
        // Блокируем стандартное поле количества
        $('input.qty').prop('readonly', true).css('background-color', '#f5f5f5');
        
        console.log('✓ Square Meter Calculator initialized');
    });
    </script>
    <?php
}
