<?php
/**
 * Калькулятор: Размеры (Dimensions Calculator)
 * Описание: Калькулятор размеров для столярных изделий с множителем
 * Категории: 265-271 (столярные изделия)
 * Зависимости: category-helpers, product-calculations
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Вывод калькулятора размеров на странице товара
 */
add_action('woocommerce_before_add_to_cart_button', 'display_dimensions_calculator', 5);
function display_dimensions_calculator() {
    global $product;
    
    if (!$product) {
        return;
    }
    
    $product_id = $product->get_id();
    
    // Проверяем нужен ли калькулятор размеров
    if (!function_exists('get_calculator_type') || get_calculator_type($product_id) !== 'dimensions') {
        return;
    }
    
    // Получаем данные товара
    $base_price = $product->get_price();
    $multiplier = function_exists('get_final_multiplier') ? get_final_multiplier($product_id) : 1.0;
    
    // Получаем диапазоны размеров
    $ranges = function_exists('get_dimension_ranges') ? get_dimension_ranges($product_id) : false;
    
    if (!$ranges) {
        return;
    }
    
    $width_min = $ranges['width']['min'];
    $width_max = $ranges['width']['max'];
    $width_step = $ranges['width']['step'];
    
    $length_min = $ranges['length']['min'];
    $length_max = $ranges['length']['max'];
    $length_step = $ranges['length']['step'];
    
    ?>
    <div id="dimensions-calculator" class="parusweb-calculator" style="margin: 20px 0; padding: 20px; border: 2px solid #2196F3; border-radius: 8px; background: #f9f9f9;">
        <h4 style="margin-top: 0; color: #1565c0;">Калькулятор размеров</h4>
        
        <?php if ($multiplier > 1): ?>
        <div style="margin-bottom: 15px; padding: 12px; background: #e3f2fd; border-radius: 4px; border-left: 4px solid #2196F3;">
            <strong>Множитель:</strong> ×<?php echo number_format($multiplier, 1, ',', ' '); ?>
        </div>
        <?php endif; ?>
        
        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Ширина (мм):</label>
            <select id="dim_width" name="custom_width_val" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px;">
                <option value="">Выберите...</option>
                <?php
                for ($w = $width_min; $w <= $width_max; $w += $width_step) {
                    echo '<option value="' . $w . '">' . $w . ' мм</option>';
                }
                ?>
            </select>
        </div>
        
        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Длина (м):</label>
            <select id="dim_length" name="custom_length_val" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px;">
                <option value="">Выберите...</option>
                <?php
                $length = $length_min;
                while ($length <= $length_max) {
                    echo '<option value="' . $length . '">' . number_format($length, 2, ',', ' ') . ' м</option>';
                    $length += $length_step;
                }
                ?>
            </select>
        </div>
        
        <div id="dim_calc_result" style="padding: 15px; background: #fff; border: 2px solid #2196F3; border-radius: 6px; display: none; margin-top: 15px;">
            <div style="margin-bottom: 10px;">
                <strong>Размер:</strong> <span id="dim_size_display">-</span>
            </div>
            <div style="margin-bottom: 10px;">
                <strong>Площадь:</strong> <span id="dim_total_area">0</span> м²
            </div>
            <div style="font-size: 20px; color: #2196F3; font-weight: 700;">
                <strong>Итого:</strong> <span id="dim_total_price">0 ₽</span>
            </div>
        </div>
        
        <!-- Скрытые поля -->
        <input type="hidden" id="dim_price" name="custom_dim_price" value="0">
        <input type="hidden" id="dim_grand_total" name="custom_dim_grand_total" value="0">
        <input type="hidden" id="dim_base_price" value="<?php echo esc_attr($base_price); ?>">
        <input type="hidden" id="dim_multiplier" value="<?php echo esc_attr($multiplier); ?>">
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        'use strict';
        
        // Функция обновления расчетов
        function updateDimensionsCalculator() {
            const width = parseFloat($('#dim_width').val()) || 0;
            const length = parseFloat($('#dim_length').val()) || 0;
            const basePrice = parseFloat($('#dim_base_price').val()) || 0;
            const multiplier = parseFloat($('#dim_multiplier').val()) || 1;
            
            if (width <= 0 || length <= 0 || basePrice <= 0) {
                $('#dim_calc_result').hide();
                return;
            }
            
            // Расчет площади с множителем
            const area = (width / 1000) * length * multiplier;
            let totalPrice = area * basePrice;
            
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
            $('#dim_size_display').text(width + ' мм × ' + length.toFixed(2) + ' м');
            $('#dim_total_area').text(area.toFixed(3).replace('.', ','));
            $('#dim_total_price').text(formatPrice(grandTotal));
            
            // Обновляем скрытые поля
            $('#dim_price').val(totalPrice);
            $('#dim_grand_total').val(grandTotal);
            
            // Показываем результат
            $('#dim_calc_result').show();
            
            // Обновляем количество WooCommerce
            $('input.qty').val(1);
        }
        
        // Форматирование цены
        function formatPrice(price) {
            return Math.round(price).toLocaleString('ru-RU') + ' ₽';
        }
        
        // Обработчики изменений
        $('#dim_width, #dim_length').on('change', updateDimensionsCalculator);
        $(document).on('change', '#painting_service_select, #painting_color_select', updateDimensionsCalculator);
        
        // Блокируем стандартное поле количества
        $('input.qty').prop('readonly', true).css('background-color', '#f5f5f5');
        
        console.log('✓ Dimensions Calculator initialized');
    });
    </script>
    <?php
}
