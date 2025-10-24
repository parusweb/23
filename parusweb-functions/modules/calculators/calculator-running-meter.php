<?php
/**
 * Калькулятор: Погонные метры (Running Meter Calculator)
 * Описание: Калькулятор для товаров продаваемых на погонные метры
 * Категории: 267, 271 (столярные изделия)
 * Зависимости: category-helpers, product-calculations
 * 
 * ВАЖНО: Этот файл содержит ТОЛЬКО функцию отображения
 * Подключение через add_action происходит в calculator-display.php
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Функция вывода калькулятора погонных метров
 * Вызывается из calculator-display.php
 */
function display_running_meter_calculator($product_id, $price) {
    $base_price = $price;
    $multiplier = function_exists('get_final_multiplier') ? get_final_multiplier($product_id) : 1.0;
    
    ?>
    <div id="running-meter-calculator" class="parusweb-calculator" style="margin: 20px 0; padding: 20px; border: 2px solid #ddd; border-radius: 8px; background: #f9f9f9;">
        <h4 style="margin-top: 0;">Калькулятор погонных метров</h4>
        
        <?php if ($multiplier > 1): ?>
        <div style="margin-bottom: 15px; padding: 12px; background: #fff3cd; border-radius: 4px;">
            <strong>Множитель:</strong> ×<?php echo number_format($multiplier, 1, ',', ' '); ?>
        </div>
        <?php endif; ?>
        
        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Длина (м.п.):</label>
            <input type="number" id="rm_length" name="custom_rm_length" 
                   min="0.1" step="0.1" value="" placeholder="0.0"
                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px;">
        </div>
        
        <div id="rm_calc_result" style="padding: 15px; background: #fff; border: 2px solid #ddd; border-radius: 6px; display: none; margin-top: 15px;">
            <div style="margin-bottom: 10px;">
                <strong>Длина:</strong> <span id="rm_total_length">0</span> м.п.
            </div>
            <div style="margin-bottom: 10px;">
                <strong>Цена за м.п.:</strong> <?php echo wc_price($price); ?>
            </div>
            <div style="font-size: 18px; color: #2271b1; font-weight: 700;">
                <strong>Итого:</strong> <span id="rm_total_price">0 ₽</span>
            </div>
        </div>
        
        <!-- Скрытые поля -->
        <input type="hidden" id="rm_total_price_value" name="custom_rm_price" value="0">
        <input type="hidden" id="rm_grand_total" name="custom_rm_grand_total" value="0">
        <input type="hidden" id="rm_total_length_value" name="custom_rm_total_length" value="0">
        <input type="hidden" id="rm_quantity" name="custom_rm_quantity" value="1">
        <input type="hidden" id="rm_base_price" value="<?php echo esc_attr($base_price); ?>">
        <input type="hidden" id="rm_multiplier" value="<?php echo esc_attr($multiplier); ?>">
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        console.log('✓ Running Meter Calculator initialized');
        
        function updateRunningMeterCalculator() {
            const length = parseFloat($('#rm_length').val()) || 0;
            
            if (length <= 0) {
                $('#rm_calc_result').hide();
                return;
            }
            
            const basePrice = parseFloat($('#rm_base_price').val()) || 0;
            const multiplier = parseFloat($('#rm_multiplier').val()) || 1;
            
            const pricePerMeter = basePrice * multiplier;
            const totalPrice = length * pricePerMeter;
            
            // Получаем стоимость покраски если есть
            let paintingPrice = 0;
            if ($('#painting_service_select').length) {
                const selectedService = $('#painting_service_select option:selected');
                if (selectedService.val()) {
                    const paintingPricePerM2 = parseFloat(selectedService.data('price')) || 0;
                    // Для погонных метров нужно площадь покраски
                    // Предполагаем что это будет рассчитано отдельно
                    paintingPrice = 0; // TODO: добавить расчет площади для покраски
                }
            }
            
            const grandTotal = totalPrice + paintingPrice;
            
            // Обновляем вывод
            $('#rm_total_length').text(length.toFixed(1) + ' м.п.');
            $('#rm_total_price').text(Math.round(grandTotal).toLocaleString('ru-RU') + ' ₽');
            
            // Обновляем скрытые поля
            $('#rm_total_price_value').val(totalPrice);
            $('#rm_grand_total').val(grandTotal);
            $('#rm_total_length_value').val(length);
            
            // Показываем результат
            $('#rm_calc_result').show();
            
            // Обновляем количество WooCommerce
            $('input.qty').val(1).prop('readonly', true).css('background-color', '#f5f5f5');
        }
        
        // Обработчики изменений
        $('#rm_length').on('input', updateRunningMeterCalculator);
        $(document).on('change', '#painting_service_select', updateRunningMeterCalculator);
    });
    </script>
    <?php
}
