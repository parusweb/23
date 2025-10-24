<?php
/**
 * Калькулятор: Размеры (Dimensions Calculator)
 * Описание: Калькулятор размеров для столярных изделий с множителем
 * Категории: 265-271 (столярные изделия)
 * Зависимости: category-helpers, product-calculations
 * 
 * ВАЖНО: Этот файл содержит ТОЛЬКО функцию отображения
 * Подключение через add_action происходит в calculator-display.php
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Функция вывода калькулятора размеров
 * Вызывается из calculator-display.php
 */
function display_dimensions_calculator($product_id, $price, $area_data = null) {
    $base_price = $price;
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
    <div id="dimensions-calculator" class="parusweb-calculator" style="margin: 20px 0; padding: 20px; border: 2px solid #ddd; border-radius: 8px; background: #f9f9f9;">
        <h4 style="margin-top: 0;">Калькулятор размеров</h4>
        
        <?php if ($multiplier > 1): ?>
        <div style="margin-bottom: 15px; padding: 12px; background: #fff3cd; border-radius: 4px;">
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
                $length_min_m = $length_min / 1000;
                $length_max_m = $length_max / 1000;
                $length_step_m = $length_step / 1000;
                
                for ($l = $length_min_m; $l <= $length_max_m; $l += $length_step_m) {
                    echo '<option value="' . ($l * 1000) . '">' . number_format($l, 2, ',', '') . ' м</option>';
                }
                ?>
            </select>
        </div>
        
        <div id="dim_calc_result" style="padding: 15px; background: #fff; border: 2px solid #ddd; border-radius: 6px; display: none; margin-top: 15px;">
            <div style="margin-bottom: 10px;">
                <strong>Площадь:</strong> <span id="dim_total_area">0</span> м²
            </div>
            <?php if ($multiplier > 1): ?>
            <div style="margin-bottom: 10px;">
                <strong>Цена с множителем:</strong> <span id="dim_price_with_mult">0 ₽</span>
            </div>
            <?php endif; ?>
            <div style="font-size: 18px; color: #2271b1; font-weight: 700;">
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
        console.log('✓ Dimensions Calculator initialized');
        
        function updateDimensionsCalculator() {
            const width = parseFloat($('#dim_width').val()) || 0;
            const length = parseFloat($('#dim_length').val()) || 0;
            
            if (width <= 0 || length <= 0) {
                $('#dim_calc_result').hide();
                return;
            }
            
            const area = (width / 1000) * (length / 1000); // конвертируем в м²
            const basePrice = parseFloat($('#dim_base_price').val()) || 0;
            const multiplier = parseFloat($('#dim_multiplier').val()) || 1;
            
            const pricePerM2 = basePrice * multiplier;
            const totalPrice = area * pricePerM2;
            
            // Получаем стоимость покраски если есть
            let paintingPrice = 0;
            if ($('#painting_service_select').length) {
                const selectedService = $('#painting_service_select option:selected');
                if (selectedService.val()) {
                    const paintingPricePerM2 = parseFloat(selectedService.data('price')) || 0;
                    paintingPrice = area * paintingPricePerM2;
                }
            }
            
            const grandTotal = totalPrice + paintingPrice;
            
            // Обновляем вывод
            $('#dim_total_area').text(area.toFixed(3) + ' м²');
            $('#dim_price_with_mult').text(Math.round(totalPrice).toLocaleString('ru-RU') + ' ₽');
            $('#dim_total_price').text(Math.round(grandTotal).toLocaleString('ru-RU') + ' ₽');
            
            // Обновляем скрытые поля
            $('#dim_price').val(totalPrice);
            $('#dim_grand_total').val(grandTotal);
            
            // Показываем результат
            $('#dim_calc_result').show();
            
            // Обновляем количество WooCommerce
            $('input.qty').val(1).prop('readonly', true).css('background-color', '#f5f5f5');
        }
        
        // Обработчики изменений
        $('#dim_width, #dim_length').on('change', updateDimensionsCalculator);
        $(document).on('change', '#painting_service_select, #painting_color_select', updateDimensionsCalculator);
    });
    </script>
    <?php
}
