<?php
/**
 * Калькулятор: Погонные метры (Running Meter Calculator)
 * Описание: Калькулятор для товаров продаваемых на погонные метры
 * Категории: 267, 271 (столярные изделия)
 * Зависимости: category-helpers, product-calculations
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Вывод калькулятора погонных метров на странице товара
 */
add_action('woocommerce_before_add_to_cart_button', 'display_running_meter_calculator', 5);
function display_running_meter_calculator() {
    global $product;
    
    if (!$product) {
        return;
    }
    
    $product_id = $product->get_id();
    
    // Проверяем нужен ли калькулятор погонных метров
    if (!function_exists('get_calculator_type') || get_calculator_type($product_id) !== 'running_meter') {
        return;
    }
    
    // Получаем данные товара
    $base_price = $product->get_price();
    $multiplier = function_exists('get_final_multiplier') ? get_final_multiplier($product_id) : 1.0;
    
    ?>
    <div id="running-meter-calculator" class="parusweb-calculator" style="margin: 20px 0; padding: 20px; border: 2px solid #FF9800; border-radius: 8px; background: #f9f9f9;">
        <h4 style="margin-top: 0; color: #e65100;">Калькулятор погонных метров</h4>
        
        <?php if ($multiplier > 1): ?>
        <div style="margin-bottom: 15px; padding: 12px; background: #fff3e0; border-radius: 4px; border-left: 4px solid #FF9800;">
            <strong>Множитель:</strong> ×<?php echo number_format($multiplier, 1, ',', ' '); ?>
        </div>
        <?php endif; ?>
        
        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Длина (м.п.):</label>
            <input type="number" id="rm_length" name="custom_rm_length" 
                   min="0.1" step="0.1" value="" placeholder="0.0"
                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px;">
        </div>
        
        <div id="rm_calc_result" style="padding: 15px; background: #fff; border: 2px solid #FF9800; border-radius: 6px; display: none; margin-top: 15px;">
            <div style="margin-bottom: 10px;">
                <strong>Длина:</strong> <span id="rm_total_length">0</span> м.п.
            </div>
            <div style="margin-bottom: 10px;">
                <strong>Цена за м.п.:</strong> <span id="rm_price_per_meter">0 ₽</span>
            </div>
            <div style="font-size: 20px; color: #FF9800; font-weight: 700;">
                <strong>Итого:</strong> <span id="rm_total_price">0 ₽</span>
            </div>
        </div>
        
        <!-- Скрытые поля -->
        <input type="hidden" id="rm_price" name="custom_rm_price" value="0">
        <input type="hidden" id="rm_grand_total" name="custom_rm_grand_total" value="0">
        <input type="hidden" id="rm_quantity" name="custom_rm_quantity" value="1">
        <input type="hidden" id="rm_multiplier_val" name="custom_rm_multiplier" value="<?php echo esc_attr($multiplier); ?>">
        <input type="hidden" id="rm_base_price" value="<?php echo esc_attr($base_price); ?>">
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        'use strict';
        
        // Функция обновления расчетов
        function updateRunningMeterCalculator() {
            const length = parseFloat($('#rm_length').val()) || 0;
            const basePrice = parseFloat($('#rm_base_price').val()) || 0;
            const multiplier = parseFloat($('#rm_multiplier_val').val()) || 1;
            
            if (length <= 0 || basePrice <= 0) {
                $('#rm_calc_result').hide();
                return;
            }
            
            // Расчет цены
            const pricePerMeter = basePrice * multiplier;
            let totalPrice = length * pricePerMeter;
            
            // Добавляем стоимость покраски если выбрана
            let grandTotal = totalPrice;
            const paintingSelect = $('#painting_service_select');
            if (paintingSelect.length && paintingSelect.val()) {
                const paintingPricePerM2 = parseFloat(paintingSelect.find('option:selected').data('price')) || 0;
                if (paintingPricePerM2 > 0) {
                    // Для покраски нужна площадь: длина × ширина
                    // Предполагаем стандартную ширину или берем из данных
                    const width = parseFloat($('#rm_width').val()) || 0.1; // 100мм по умолчанию
                    const paintingArea = length * (width / 1000);
                    grandTotal += paintingArea * paintingPricePerM2;
                }
            }
            
            // Обновляем отображение
            $('#rm_total_length').text(length.toFixed(1).replace('.', ','));
            $('#rm_price_per_meter').text(formatPrice(pricePerMeter));
            $('#rm_total_price').text(formatPrice(grandTotal));
            
            // Обновляем скрытые поля
            $('#rm_price').val(totalPrice);
            $('#rm_grand_total').val(grandTotal);
            
            // Показываем результат
            $('#rm_calc_result').show();
            
            // Обновляем количество WooCommerce
            $('input.qty').val(1);
        }
        
        // Форматирование цены
        function formatPrice(price) {
            return Math.round(price).toLocaleString('ru-RU') + ' ₽';
        }
        
        // Обработчики изменений
        $('#rm_length').on('input change', updateRunningMeterCalculator);
        $(document).on('change', '#painting_service_select, #painting_color_select', updateRunningMeterCalculator);
        
        // Блокируем стандартное поле количества
        $('input.qty').prop('readonly', true).css('background-color', '#f5f5f5');
        
        console.log('✓ Running Meter Calculator initialized');
    });
    </script>
    <?php
}
