<?php
/**
 * Калькулятор: Фальшбалки (Falsebalk Calculator)
 * Описание: Калькулятор для фальшбалок с выбором формы сечения
 * Категория: 266
 * Зависимости: category-helpers, product-calculations
 * 
 * ВАЖНО: Этот файл содержит ТОЛЬКО функцию отображения
 * Подключение через add_action происходит в calculator-display.php
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Функция вывода калькулятора фальшбалок
 * Вызывается из calculator-display.php
 */
function display_falsebalk_calculator($product_id, $price) {
    // Получаем данные о формах сечения
    $shapes_data = get_post_meta($product_id, '_falsebalk_shapes_data', true);
    
    if (!is_array($shapes_data) || empty($shapes_data)) {
        return;
    }
    
    $base_price = $price;
    
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
    
    // Иконки SVG для форм
    $shape_icons = [
        'g' => '<svg width="60" height="60" viewBox="0 0 60 60"><rect x="5" y="5" width="10" height="50" fill="#000"/><rect x="5" y="45" width="50" height="10" fill="#000"/></svg>',
        'p' => '<svg width="60" height="60" viewBox="0 0 60 60"><rect x="5" y="5" width="10" height="50" fill="#000"/><rect x="45" y="5" width="10" height="50" fill="#000"/><rect x="5" y="5" width="50" height="10" fill="#000"/></svg>',
        'o' => '<svg width="60" height="60" viewBox="0 0 60 60"><rect x="5" y="5" width="50" height="50" fill="none" stroke="#000" stroke-width="10"/></svg>'
    ];
    
    ?>
    <div id="falsebalk-calculator" class="parusweb-calculator" style="margin: 20px 0; padding: 20px; border: 2px solid #ddd; border-radius: 8px; background: #f9f9f9;">
        <h4 style="margin-top: 0;">Калькулятор фальшбалок</h4>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 10px; font-weight: 600;">Выберите форму сечения:</label>
            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                <?php foreach ($shapes_data as $shape_key => $shape_info): 
                    if (is_array($shape_info) && !empty($shape_info['enabled'])): 
                        $shape_label = isset($shape_names[$shape_key]) ? $shape_names[$shape_key] : ucfirst($shape_key);
                        ?>
                        <label class="shape-tile" data-shape="<?php echo esc_attr($shape_key); ?>" 
                               style="cursor: pointer; border: 2px solid #ddd; border-radius: 10px; padding: 10px; background: #fff; display: flex; flex-direction: column; align-items: center; gap: 8px; transition: all .2s; min-width: 100px;">
                            <input type="radio" name="falsebalk_shape" value="<?php echo esc_attr($shape_key); ?>" style="display: none;">
                            <div><?php echo $shape_icons[$shape_key]; ?></div>
                            <span style="font-size: 12px; color: #666; text-align: center;"><?php echo esc_html($shape_label); ?></span>
                        </label>
                    <?php endif; 
                endforeach; ?>
            </div>
        </div>
        
        <div id="falsebalk_dimensions" style="display: none;">
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Ширина (мм):</label>
                <select id="fb_width" name="custom_rm_width" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px;">
                    <option value="">Выберите...</option>
                </select>
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Высота (мм):</label>
                <select id="fb_height" name="custom_rm_height" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px;">
                    <option value="">Выберите...</option>
                </select>
            </div>
            
            <div id="fb_height2_container" style="margin-bottom: 15px; display: none;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Высота 2 (мм):</label>
                <select id="fb_height2" name="custom_rm_height2" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px;">
                    <option value="">Выберите...</option>
                </select>
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Длина (м):</label>
                <input type="number" id="fb_length" name="custom_rm_length" 
                       min="0.1" step="0.1" value="" placeholder="0.0"
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px;">
            </div>
            
            <div id="fb_calc_result" style="padding: 15px; background: #fff; border: 2px solid #ddd; border-radius: 6px; display: none; margin-top: 15px;">
                <div style="margin-bottom: 10px;">
                    <strong>Площадь покраски:</strong> <span id="fb_paint_area">0</span> м²
                </div>
                <div style="margin-bottom: 10px;">
                    <strong>Цена за м.п.:</strong> <span id="fb_price_per_meter">0 ₽</span>
                </div>
                <div style="font-size: 18px; color: #2271b1; font-weight: 700;">
                    <strong>Итого:</strong> <span id="fb_total_price">0 ₽</span>
                </div>
            </div>
        </div>
        
        <!-- Скрытые поля -->
        <input type="hidden" id="fb_shape_value" name="custom_rm_shape" value="">
        <input type="hidden" id="fb_shape_label" name="custom_rm_shape_label" value="">
        <input type="hidden" id="fb_price" name="custom_rm_price" value="0">
        <input type="hidden" id="fb_grand_total" name="custom_rm_grand_total" value="0">
        <input type="hidden" id="fb_painting_area" name="custom_rm_painting_area" value="0">
        <input type="hidden" id="fb_multiplier_value" name="custom_rm_multiplier" value="1">
        <input type="hidden" id="fb_quantity" name="custom_rm_quantity" value="1">
        <input type="hidden" id="fb_base_price" value="<?php echo esc_attr($base_price); ?>">
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        console.log('✓ Falsebalk Calculator initialized');
        
        const shapesData = <?php echo json_encode($shapes_data); ?>;
        const formMultipliers = <?php echo json_encode($form_multipliers); ?>;
        const shapeNames = <?php echo json_encode($shape_names); ?>;
        const basePrice = parseFloat($('#fb_base_price').val()) || 0;
        
        let currentShape = null;
        
        // Выбор формы
        $('.shape-tile').on('click', function() {
            $('.shape-tile').css({
                'border-color': '#ddd',
                'background': '#fff'
            });
            
            $(this).css({
                'border-color': '#2271b1',
                'background': '#e8f4f8'
            });
            
            $(this).find('input[type="radio"]').prop('checked', true);
            
            currentShape = $(this).data('shape');
            $('#fb_shape_value').val(currentShape);
            $('#fb_shape_label').val(shapeNames[currentShape]);
            $('#fb_multiplier_value').val(formMultipliers[currentShape]);
            
            loadShapeData(currentShape);
            $('#falsebalk_dimensions').show();
            $('#fb_calc_result').hide();
        });
        
        function loadShapeData(shape) {
            const data = shapesData[shape];
            if (!data) return;
            
            // Загружаем ширину
            $('#fb_width').html('<option value="">Выберите...</option>');
            if (data.widths && data.widths.length) {
                data.widths.forEach(w => {
                    $('#fb_width').append(`<option value="${w}">${w} мм</option>`);
                });
            }
            
            // Загружаем высоту
            $('#fb_height').html('<option value="">Выберите...</option>');
            if (data.heights && data.heights.length) {
                data.heights.forEach(h => {
                    $('#fb_height').append(`<option value="${h}">${h} мм</option>`);
                });
            }
            
            // Высота 2 только для П-образных
            if (shape === 'p' && data.heights2 && data.heights2.length) {
                $('#fb_height2_container').show();
                $('#fb_height2').html('<option value="">Выберите...</option>');
                data.heights2.forEach(h2 => {
                    $('#fb_height2').append(`<option value="${h2}">${h2} мм</option>`);
                });
            } else {
                $('#fb_height2_container').hide();
            }
        }
        
        function calculateFalsebalk() {
            const width = parseFloat($('#fb_width').val()) || 0;
            const height = parseFloat($('#fb_height').val()) || 0;
            const height2 = parseFloat($('#fb_height2').val()) || 0;
            const length = parseFloat($('#fb_length').val()) || 0;
            
            if (width <= 0 || height <= 0 || length <= 0 || !currentShape) {
                $('#fb_calc_result').hide();
                return;
            }
            
            // П-образная требует обе высоты
            if (currentShape === 'p' && height2 <= 0) {
                $('#fb_calc_result').hide();
                return;
            }
            
            const formMult = formMultipliers[currentShape];
            
            // Рассчитываем площадь покраски
            let paintArea = 0;
            if (currentShape === 'g') {
                paintArea = ((width + height) / 1000) * length;
            } else if (currentShape === 'p') {
                paintArea = ((width + height + height2) / 1000) * length;
            } else if (currentShape === 'o') {
                paintArea = ((width * 2 + height * 2) / 1000) * length;
            }
            
            const pricePerMeter = (width / 1000) * basePrice * formMult;
            const totalPrice = pricePerMeter * length;
            
            // Получаем стоимость покраски если есть
            let paintingPrice = 0;
            if ($('#painting_service_select').length) {
                const selectedService = $('#painting_service_select option:selected');
                if (selectedService.val()) {
                    const paintingPricePerM2 = parseFloat(selectedService.data('price')) || 0;
                    paintingPrice = paintArea * paintingPricePerM2;
                }
            }
            
            const grandTotal = totalPrice + paintingPrice;
            
            // Обновляем вывод
            $('#fb_paint_area').text(paintArea.toFixed(2) + ' м²');
            $('#fb_price_per_meter').text(Math.round(pricePerMeter).toLocaleString('ru-RU') + ' ₽');
            $('#fb_total_price').text(Math.round(grandTotal).toLocaleString('ru-RU') + ' ₽');
            
            // Обновляем скрытые поля
            $('#fb_price').val(totalPrice);
            $('#fb_grand_total').val(grandTotal);
            $('#fb_painting_area').val(paintArea);
            
            // Показываем результат
            $('#fb_calc_result').show();
            
            // Обновляем количество WooCommerce
            $('input.qty').val(1).prop('readonly', true).css('background-color', '#f5f5f5');
        }
        
        // Обработчики изменений
        $('#fb_width, #fb_height, #fb_height2, #fb_length').on('change input', calculateFalsebalk);
        $(document).on('change', '#painting_service_select', calculateFalsebalk);
    });
    </script>
    <?php
}
