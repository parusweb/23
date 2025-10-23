<?php
/**
 * Калькулятор: Фальшбалки (Falsebalk Calculator)
 * Описание: Калькулятор для фальшбалок с выбором формы сечения
 * Категория: 266
 * Зависимости: category-helpers, product-calculations
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Вывод калькулятора фальшбалок на странице товара
 */
add_action('woocommerce_before_add_to_cart_button', 'display_falsebalk_calculator', 5);
function display_falsebalk_calculator() {
    global $product;
    
    if (!$product) {
        return;
    }
    
    $product_id = $product->get_id();
    
    // Проверяем нужен ли калькулятор фальшбалок
    if (!function_exists('get_calculator_type') || get_calculator_type($product_id) !== 'falsebalk') {
        return;
    }
    
    // Получаем данные о формах сечения
    $shapes_data = get_post_meta($product_id, '_falsebalk_shapes_data', true);
    
    if (!is_array($shapes_data) || empty($shapes_data)) {
        return;
    }
    
    $base_price = $product->get_price();
    
    // Названия форм
    $shape_names = [
        'g' => 'Г-образная',
        'p' => 'П-образная',
        'o' => 'О-образная (квадрат)'
    ];
    
    // Множители для форм
    $form_multipliers = [
        'g' => 2,
        'p' => 3,
        'o' => 4
    ];
    
    ?>
    <div id="falsebalk-calculator" class="parusweb-calculator" style="margin: 20px 0; padding: 20px; border: 2px solid #795548; border-radius: 8px; background: #f9f9f9;">
        <h4 style="margin-top: 0; color: #4e342e;">Калькулятор фальшбалок</h4>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 10px; font-weight: 600;">Выберите форму сечения:</label>
            <div id="falsebalk_shapes" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px;">
                <?php foreach ($shapes_data as $shape_key => $shape_info): ?>
                    <?php if (!empty($shape_info['enabled'])): ?>
                    <div class="shape-tile" data-shape="<?php echo esc_attr($shape_key); ?>" 
                         style="padding: 15px; border: 2px solid #ddd; border-radius: 8px; text-align: center; cursor: pointer; transition: all 0.3s;">
                        <input type="radio" name="falsebalk_shape" value="<?php echo esc_attr($shape_key); ?>" 
                               id="shape_<?php echo esc_attr($shape_key); ?>" style="display: none;">
                        <?php if (!empty($shape_info['image'])): ?>
                            <img src="<?php echo esc_url($shape_info['image']); ?>" 
                                 style="max-width: 80px; height: auto; margin-bottom: 10px;" 
                                 alt="<?php echo esc_attr($shape_names[$shape_key]); ?>">
                        <?php endif; ?>
                        <div style="font-weight: 600; font-size: 14px;"><?php echo esc_html($shape_names[$shape_key]); ?></div>
                        <div style="font-size: 12px; color: #666; margin-top: 5px;">×<?php echo $form_multipliers[$shape_key]; ?></div>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div id="falsebalk_params" style="display: none;">
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Ширина (мм):</label>
                <select id="fb_width" name="custom_rm_width" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="">Выберите...</option>
                </select>
            </div>
            
            <div id="height_container"></div>
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Длина (м):</label>
                <select id="fb_length" name="custom_rm_length" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="">Выберите...</option>
                </select>
            </div>
            
            <div id="fb_calc_result" style="padding: 15px; background: #fff; border: 2px solid #795548; border-radius: 6px; display: none; margin-top: 15px;">
                <div style="margin-bottom: 10px;">
                    <strong>Площадь покраски:</strong> <span id="fb_painting_area">0</span> м²
                </div>
                <div style="font-size: 20px; color: #795548; font-weight: 700;">
                    <strong>Итого:</strong> <span id="fb_total_price">0 ₽</span>
                </div>
            </div>
        </div>
        
        <!-- Скрытые поля -->
        <input type="hidden" id="fb_shape_value" name="custom_rm_shape" value="">
        <input type="hidden" id="fb_shape_label" name="custom_rm_shape_label" value="">
        <input type="hidden" id="fb_price" name="custom_rm_price" value="0">
        <input type="hidden" id="fb_grand_total" name="custom_rm_grand_total" value="0">
        <input type="hidden" id="fb_quantity" name="custom_rm_quantity" value="1">
        <input type="hidden" id="fb_multiplier" name="custom_rm_multiplier" value="1">
        <input type="hidden" id="fb_painting_area_val" name="custom_rm_painting_area" value="0">
        <input type="hidden" id="fb_total_length_val" name="custom_rm_total_length" value="0">
        <input type="hidden" id="fb_base_price" value="<?php echo esc_attr($base_price); ?>">
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        'use strict';
        
        // Данные форм
        const shapesData = <?php echo json_encode($shapes_data); ?>;
        const formMultipliers = <?php echo json_encode($form_multipliers); ?>;
        const shapeNames = <?php echo json_encode($shape_names); ?>;
        
        // Функция генерации опций
        function generateOptions(min, max, step, unit = '') {
            let options = '<option value="">Выберите...</option>';
            if (!min || !max || !step || min > max) return options;
            
            for (let val = min; val <= max; val += step) {
                const displayValue = unit === 'м' ? val.toFixed(2) : Math.round(val);
                options += `<option value="${val}">${displayValue}${unit ? ' ' + unit : ''}</option>`;
            }
            return options;
        }
        
        // Функция обновления размеров
        function updateDimensions(selectedShape) {
            const shapeData = shapesData[selectedShape];
            
            if (!shapeData || !shapeData.enabled) {
                return;
            }
            
            $('#falsebalk_params').show();
            
            // ШИРИНЫ
            $('#fb_width').html(generateOptions(shapeData.width_min, shapeData.width_max, shapeData.width_step, 'мм'));
            
            // ВЫСОТЫ
            $('#height_container').empty();
            if (selectedShape === 'p') {
                // П-образная: две высоты
                $('#height_container').html(`
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">Высота 1 (мм):</label>
                        <select id="fb_height1" name="custom_rm_height" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                            ${generateOptions(shapeData.height1_min, shapeData.height1_max, shapeData.height1_step, 'мм')}
                        </select>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">Высота 2 (мм):</label>
                        <select id="fb_height2" name="custom_rm_height2" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                            ${generateOptions(shapeData.height2_min, shapeData.height2_max, shapeData.height2_step, 'мм')}
                        </select>
                    </div>
                `);
            } else {
                // Г и О: одна высота
                $('#height_container').html(`
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">Высота (мм):</label>
                        <select id="fb_height" name="custom_rm_height" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                            ${generateOptions(shapeData.height_min, shapeData.height_max, shapeData.height_step, 'мм')}
                        </select>
                    </div>
                `);
            }
            
            // ДЛИНЫ
            $('#fb_length').html(generateOptions(shapeData.length_min, shapeData.length_max, shapeData.length_step, 'м'));
            
            // Обработчики изменений
            $('#fb_width, #fb_length, #fb_height, #fb_height1, #fb_height2').on('change', updateFalsebalkCalculator);
            
            $('#fb_calc_result').hide();
        }
        
        // Функция обновления расчетов
        function updateFalsebalkCalculator() {
            const selectedShape = $('input[name="falsebalk_shape"]:checked').val();
            if (!selectedShape) return;
            
            const width = parseFloat($('#fb_width').val()) || 0;
            const length = parseFloat($('#fb_length').val()) || 0;
            const basePrice = parseFloat($('#fb_base_price').val()) || 0;
            const multiplier = formMultipliers[selectedShape] || 1;
            
            let height1 = 0, height2 = 0;
            
            if (selectedShape === 'p') {
                height1 = parseFloat($('#fb_height1').val()) || 0;
                height2 = parseFloat($('#fb_height2').val()) || 0;
                if (width <= 0 || length <= 0 || height1 <= 0 || height2 <= 0) {
                    $('#fb_calc_result').hide();
                    return;
                }
            } else {
                height1 = parseFloat($('#fb_height').val()) || 0;
                if (width <= 0 || length <= 0 || height1 <= 0) {
                    $('#fb_calc_result').hide();
                    return;
                }
            }
            
            // Расчет площади покраски: периметр × длина
            const paintingArea = (width / 1000) * length * multiplier;
            let totalPrice = paintingArea * basePrice;
            
            // Добавляем стоимость покраски если выбрана
            let grandTotal = totalPrice;
            const paintingSelect = $('#painting_service_select');
            if (paintingSelect.length && paintingSelect.val()) {
                const paintingPricePerM2 = parseFloat(paintingSelect.find('option:selected').data('price')) || 0;
                if (paintingPricePerM2 > 0) {
                    grandTotal += paintingArea * paintingPricePerM2;
                }
            }
            
            // Обновляем отображение
            $('#fb_painting_area').text(paintingArea.toFixed(2).replace('.', ','));
            $('#fb_total_price').text(formatPrice(grandTotal));
            
            // Обновляем скрытые поля
            $('#fb_shape_value').val(selectedShape);
            $('#fb_shape_label').val(shapeNames[selectedShape]);
            $('#fb_price').val(totalPrice);
            $('#fb_grand_total').val(grandTotal);
            $('#fb_multiplier').val(multiplier);
            $('#fb_painting_area_val').val(paintingArea);
            $('#fb_total_length_val').val(length);
            
            // Показываем результат
            $('#fb_calc_result').show();
            
            // Обновляем количество WooCommerce
            $('input.qty').val(1);
        }
        
        // Форматирование цены
        function formatPrice(price) {
            return Math.round(price).toLocaleString('ru-RU') + ' ₽';
        }
        
        // Обработчик клика по плитке формы
        $('.shape-tile').on('click', function() {
            $('.shape-tile').css({
                'border-color': '#ddd',
                'box-shadow': 'none'
            });
            
            $(this).css({
                'border-color': '#795548',
                'box-shadow': '0 0 0 3px rgba(121, 85, 72, 0.3)'
            });
            
            const shape = $(this).data('shape');
            $('#shape_' + shape).prop('checked', true);
            updateDimensions(shape);
        });
        
        // Обработчик изменения покраски
        $(document).on('change', '#painting_service_select, #painting_color_select', updateFalsebalkCalculator);
        
        // Блокируем стандартное поле количества
        $('input.qty').prop('readonly', true).css('background-color', '#f5f5f5');
        
        console.log('✓ Falsebalk Calculator initialized');
    });
    </script>
    <?php
}
