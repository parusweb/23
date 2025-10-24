<?php
/**
 * Калькулятор: Квадратные метры (Square Meter Calculator)
 * Описание: Калькулятор для товаров продаваемых на квадратные метры
 * Категории: 266, 268, 270 (столярные изделия)
 * Зависимости: category-helpers, product-calculations
 * 
 * ВАЖНО: Этот файл содержит ТОЛЬКО функцию отображения
 * Подключение через add_action происходит в calculator-display.php
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Функция вывода калькулятора квадратных метров
 * Вызывается из calculator-display.php
 */
function display_square_meter_calculator($product_id, $price, $area_data = null) {
    $base_price = $price;
    $product = wc_get_product($product_id);
    $title = $product ? $product->get_title() : '';
    
    if (!$area_data) {
        $area_data = function_exists('extract_area_with_qty') ? extract_area_with_qty($title, $product_id) : null;
    }
    
    ?>
    <div id="square-meter-calculator" class="parusweb-calculator" style="margin: 20px 0; padding: 20px; border: 2px solid #ddd; border-radius: 8px; background: #f9f9f9;">
        <h4 style="margin-top: 0;">Калькулятор по площади</h4>
        
        <?php if ($area_data): ?>
            <div style="margin-bottom: 15px; padding: 10px; background: #e8f4f8; border-radius: 4px;">
                <strong>Площадь в упаковке:</strong> <?php echo number_format($area_data, 2, ',', ' '); ?> м²
            </div>
        <?php endif; ?>
        
        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Ширина (м):</label>
            <input type="number" id="sq_width" name="custom_sq_width" 
                   step="0.01" min="0" placeholder="0.00"
                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px;">
        </div>
        
        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Длина (м):</label>
            <input type="number" id="sq_length" name="custom_sq_length" 
                   step="0.01" min="0" placeholder="0.00"
                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px;">
        </div>
        
        <div id="sq_calc_result" style="padding: 15px; background: #fff; border: 2px solid #ddd; border-radius: 6px; display: none; margin-top: 15px;">
            <div style="margin-bottom: 10px;">
                <strong>Площадь:</strong> <span id="sq_total_area">0</span> м²
            </div>
            <?php if ($area_data): ?>
            <div style="margin-bottom: 10px;">
                <strong>Количество упаковок:</strong> <span id="sq_packs_needed">0</span>
            </div>
            <?php endif; ?>
            <div style="font-size: 18px; color: #2271b1; font-weight: 700;">
                <strong>Итого:</strong> <span id="sq_total_price">0 ₽</span>
            </div>
        </div>
        
        <!-- Скрытые поля -->
        <input type="hidden" id="sq_total_price_value" name="custom_sq_total_price" value="0">
        <input type="hidden" id="sq_grand_total" name="custom_sq_grand_total" value="0">
        <input type="hidden" id="sq_quantity" name="custom_sq_quantity" value="1">
        <input type="hidden" id="sq_pack_area" value="<?php echo esc_attr($area_data); ?>">
        <input type="hidden" id="sq_base_price" value="<?php echo esc_attr($base_price); ?>">
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        console.log('✓ Square Meter Calculator initialized');
        
        function updateSquareMeterCalculator() {
            const width = parseFloat($('#sq_width').val()) || 0;
            const length = parseFloat($('#sq_length').val()) || 0;
            
            if (width <= 0 || length <= 0) {
                $('#sq_calc_result').hide();
                return;
            }
            
            const area = width * length;
            const basePrice = parseFloat($('#sq_base_price').val()) || 0;
            const packArea = parseFloat($('#sq_pack_area').val()) || 0;
            
            let totalPrice = area * basePrice;
            let packs = 1;
            
            if (packArea > 0) {
                packs = Math.ceil(area / packArea);
                totalPrice = packs * packArea * basePrice;
            }
            
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
            $('#sq_total_area').text(area.toFixed(2) + ' м²');
            if (packArea > 0) {
                $('#sq_packs_needed').text(packs);
            }
            $('#sq_total_price').text(Math.round(grandTotal).toLocaleString('ru-RU') + ' ₽');
            
            // Обновляем скрытые поля
            $('#sq_total_price_value').val(totalPrice);
            $('#sq_grand_total').val(grandTotal);
            $('#sq_quantity').val(packs);
            
            // Показываем результат
            $('#sq_calc_result').show();
            
            // Обновляем количество WooCommerce
            $('input.qty').val(packs).prop('readonly', true).css('background-color', '#f5f5f5');
        }
        
        // Обработчики изменений
        $('#sq_width, #sq_length').on('input', updateSquareMeterCalculator);
        $(document).on('change', '#painting_service_select, #painting_color_select', updateSquareMeterCalculator);
    });
    </script>
    <?php
}
