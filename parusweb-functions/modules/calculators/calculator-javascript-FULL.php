<?php
/**
 * Модуль: JavaScript для калькуляторов
 * Описание: Общий JavaScript код для всех калькуляторов
 * Зависимости: category-helpers, product-calculations, pm-paint-schemes
 * 
 * ЭТОТ МОДУЛЬ СОДЕРЖИТ ВЕСЬ JAVASCRIPT КОД ДЛЯ ВСЕХ КАЛЬКУЛЯТОРОВ
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Вывод JavaScript кода для калькуляторов
 */
add_action('wp_footer', 'parusweb_calculator_javascript', 100);
function parusweb_calculator_javascript() {
    if (!is_product()) {
        return;
    }
    
    global $product;
    if (!$product) {
        return;
    }
    
    $product_id = $product->get_id();
    
    // Проверяем, нужен ли калькулятор для этого товара
    if (!function_exists('get_calculator_type')) {
        return;
    }
    
    $calc_type = get_calculator_type($product_id);
    
    if ($calc_type === 'none') {
        return;
    }
    
    // Получаем данные для JavaScript
    $base_price = floatval($product->get_price());
    $painting_services = function_exists('get_available_painting_services_by_material') 
        ? get_available_painting_services_by_material($product_id) 
        : array();
    
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        'use strict';
        
        console.log('🚀 ParusWeb Calculator JavaScript loaded');
        
        // ========================================
        // ОБЩИЕ ФУНКЦИИ
        // ========================================
        
        /**
         * Форматирование цены
         */
        function formatPrice(price) {
            return Math.round(price).toLocaleString('ru-RU') + ' ₽';
        }
        
        /**
         * Функция склонения (1 штука, 2 штуки, 5 штук)
         */
        function getRussianPlural(number, forms) {
            // forms = ['штука', 'штуки', 'штук']
            const cases = [2, 0, 1, 1, 1, 2];
            const n = Math.abs(number) % 100;
            const n1 = n % 10;
            
            if (n > 10 && n < 20) {
                return forms[2];
            }
            if (n1 > 1 && n1 < 5) {
                return forms[1];
            }
            if (n1 === 1) {
                return forms[0];
            }
            return forms[2];
        }
        
        /**
         * Создание скрытого поля
         */
        function createHiddenField(name, value) {
            // Удаляем старое поле если есть
            $('input[name="' + name + '"]').remove();
            
            // Создаем новое
            const input = $('<input>').attr({
                type: 'hidden',
                name: name,
                value: value
            });
            
            $('.single_add_to_cart_button').closest('form').append(input);
        }
        
        /**
         * Удаление скрытых полей по префиксу
         */
        function removeHiddenFields(prefix) {
            $('input[name^="' + prefix + '"]').remove();
        }
        
        // ========================================
        // КАЛЬКУЛЯТОР ПЛОЩАДИ
        // ========================================
        
        const areaPacks = $('#area_packs');
        const areaResult = $('#area_calc_result');
        
        if (areaPacks.length) {
            console.log('✓ Area Calculator found');
            
            function updateAreaCalculator() {
                const packs = parseInt(areaPacks.val()) || 1;
                const packArea = parseFloat($('#area_pack_area').val()) || 0;
                const basePrice = parseFloat($('#area_base_price').val()) || 0;
                const isLeaf = $('#area_is_leaf').val() === '1';
                
                if (packs < 1 || packArea <= 0 || basePrice <= 0) {
                    return;
                }
                
                const totalArea = packs * packArea;
                const totalPrice = totalArea * basePrice;
                
                // Добавляем стоимость покраски
                let grandTotal = totalPrice;
                const paintingSelect = $('#painting_service_select');
                if (paintingSelect.length && paintingSelect.val()) {
                    const paintingPrice = parseFloat(paintingSelect.find('option:selected').data('price')) || 0;
                    if (paintingPrice > 0) {
                        grandTotal += totalArea * paintingPrice;
                    }
                }
                
                // Склонение
                const unitForms = isLeaf ? ['лист', 'листа', 'листов'] : ['упаковка', 'упаковки', 'упаковок'];
                const plural = getRussianPlural(packs, unitForms);
                
                // Обновляем отображение
                $('#area_total_area').text(totalArea.toFixed(2).replace('.', ',') + ' м²');
                $('#area_total_price').text(formatPrice(grandTotal));
                
                // Обновляем скрытые поля
                createHiddenField('custom_area_packs', packs);
                createHiddenField('custom_area_area_value', totalArea.toFixed(2));
                createHiddenField('custom_area_total_price', totalPrice.toFixed(2));
                createHiddenField('custom_area_grand_total', grandTotal.toFixed(2));
                
                // Обновляем количество WooCommerce
                $('input.qty').val(packs).prop('readonly', true);
            }
            
            areaPacks.on('input change', updateAreaCalculator);
            $(document).on('change', '#painting_service_select', updateAreaCalculator);
            
            // Инициализация
            updateAreaCalculator();
        }
        
        // ========================================
        // КАЛЬКУЛЯТОР КВАДРАТНЫХ МЕТРОВ
        // ========================================
        
        const sqWidth = $('#sq_width');
        const sqLength = $('#sq_length');
        const sqResult = $('#sq_calc_result');
        
        if (sqWidth.length && sqLength.length) {
            console.log('✓ Square Meter Calculator found');
            
            function updateSquareMeterCalculator() {
                const width = parseFloat(sqWidth.val()) || 0;
                const length = parseFloat(sqLength.val()) || 0;
                const basePrice = parseFloat($('#sq_base_price').val()) || 0;
                const packArea = parseFloat($('#sq_pack_area').val()) || 0;
                
                if (width <= 0 || length <= 0 || basePrice <= 0) {
                    sqResult.hide();
                    return;
                }
                
                const area = width * length;
                let packs = 1;
                let totalPrice = area * basePrice;
                
                if (packArea > 0) {
                    packs = Math.ceil(area / packArea);
                    totalPrice = packs * packArea * basePrice;
                }
                
                // Добавляем покраску
                let grandTotal = totalPrice;
                const paintingSelect = $('#painting_service_select');
                if (paintingSelect.length && paintingSelect.val()) {
                    const paintingPrice = parseFloat(paintingSelect.find('option:selected').data('price')) || 0;
                    if (paintingPrice > 0) {
                        grandTotal += area * paintingPrice;
                    }
                }
                
                // Обновляем отображение
                $('#sq_total_area').text(area.toFixed(2).replace('.', ','));
                if (packArea > 0) {
                    const unitForms = ['упаковка', 'упаковки', 'упаковок'];
                    $('#sq_packs_needed').text(packs + ' ' + getRussianPlural(packs, unitForms));
                }
                $('#sq_total_price').text(formatPrice(grandTotal));
                
                // Скрытые поля
                createHiddenField('custom_sq_width', width);
                createHiddenField('custom_sq_length', length);
                createHiddenField('custom_sq_total_price', grandTotal.toFixed(2));
                createHiddenField('custom_sq_quantity', packs);
                createHiddenField('custom_sq_total_area', area.toFixed(3));
                
                sqResult.show();
                $('input.qty').val(packs).prop('readonly', true);
            }
            
            sqWidth.on('input change', updateSquareMeterCalculator);
            sqLength.on('input change', updateSquareMeterCalculator);
            $(document).on('change', '#painting_service_select', updateSquareMeterCalculator);
        }
        
        // ========================================
        // КАЛЬКУЛЯТОР ПОГОННЫХ МЕТРОВ
        // ========================================
        
        const rmLength = $('#rm_length');
        const rmResult = $('#rm_calc_result');
        
        if (rmLength.length) {
            console.log('✓ Running Meter Calculator found');
            
            function updateRunningMeterCalculator() {
                const length = parseFloat(rmLength.val()) || 0;
                const basePrice = parseFloat($('#rm_base_price').val()) || 0;
                const width = parseFloat($('#rm_width').val()) || 0;
                
                if (length <= 0 || basePrice <= 0) {
                    rmResult.hide();
                    return;
                }
                
                let totalPrice = length * basePrice;
                
                // Площадь для покраски (если указана ширина)
                const paintArea = width > 0 ? (width / 1000) * length : length;
                
                // Добавляем покраску
                let grandTotal = totalPrice;
                const paintingSelect = $('#painting_service_select');
                if (paintingSelect.length && paintingSelect.val()) {
                    const paintingPrice = parseFloat(paintingSelect.find('option:selected').data('price')) || 0;
                    if (paintingPrice > 0) {
                        grandTotal += paintArea * paintingPrice;
                    }
                }
                
                // Обновляем отображение
                $('#rm_total_length').text(length.toFixed(1).replace('.', ','));
                $('#rm_total_price').text(formatPrice(grandTotal));
                
                // Скрытые поля
                createHiddenField('custom_rm_length', length);
                createHiddenField('custom_rm_width', width);
                createHiddenField('custom_rm_price', totalPrice.toFixed(2));
                createHiddenField('custom_rm_grand_total', grandTotal.toFixed(2));
                createHiddenField('custom_rm_painting_area', paintArea.toFixed(3));
                
                rmResult.show();
                $('input.qty').val(1).prop('readonly', true);
            }
            
            rmLength.on('input change', updateRunningMeterCalculator);
            $(document).on('change', '#painting_service_select', updateRunningMeterCalculator);
        }
        
        // ========================================
        // КАЛЬКУЛЯТОР РАЗМЕРОВ
        // ========================================
        
        const dimWidth = $('#dim_width');
        const dimLength = $('#dim_length');
        const dimResult = $('#dim_calc_result');
        
        if (dimWidth.length && dimLength.length) {
            console.log('✓ Dimensions Calculator found');
            
            function updateDimensionsCalculator() {
                const width = parseInt(dimWidth.val()) || 0;
                const length = parseFloat(dimLength.val()) || 0;
                const basePrice = parseFloat($('#dim_base_price').val()) || 0;
                const multiplier = parseFloat($('#dim_multiplier').val()) || 1;
                
                if (width <= 0 || length <= 0 || basePrice <= 0) {
                    dimResult.hide();
                    return;
                }
                
                const area = (width / 1000) * length;
                let totalPrice = area * basePrice * multiplier;
                
                // Добавляем покраску
                let grandTotal = totalPrice;
                const paintingSelect = $('#painting_service_select');
                if (paintingSelect.length && paintingSelect.val()) {
                    const paintingPrice = parseFloat(paintingSelect.find('option:selected').data('price')) || 0;
                    if (paintingPrice > 0) {
                        grandTotal += area * paintingPrice;
                    }
                }
                
                // Обновляем отображение
                $('#dim_width_display').text(width + ' мм');
                $('#dim_length_display').text(length.toFixed(2).replace('.', ',') + ' м');
                $('#dim_total_area').text(area.toFixed(3).replace('.', ','));
                $('#dim_total_price').text(formatPrice(grandTotal));
                
                // Скрытые поля
                createHiddenField('custom_width_val', width);
                createHiddenField('custom_length_val', length);
                createHiddenField('custom_dim_price', totalPrice.toFixed(2));
                createHiddenField('custom_dim_grand_total', grandTotal.toFixed(2));
                createHiddenField('custom_dim_area', area.toFixed(3));
                
                dimResult.show();
                $('input.qty').val(1).prop('readonly', true);
            }
            
            dimWidth.on('change', updateDimensionsCalculator);
            dimLength.on('change', updateDimensionsCalculator);
            $(document).on('change', '#painting_service_select', updateDimensionsCalculator);
        }
        
        // ========================================
        // КАЛЬКУЛЯТОР ФАЛЬШБАЛОК
        // ========================================
        
        const fbShape = $('#fb_shape');
        const fbLength = $('#fb_length');
        const fbResult = $('#fb_calc_result');
        
        if (fbShape.length && fbLength.length) {
            console.log('✓ Falsebalk Calculator found');
            
            function updateFalsebalkCalculator() {
                const shape = fbShape.val();
                const length = parseFloat(fbLength.val()) || 0;
                const basePrice = parseFloat($('#fb_base_price').val()) || 0;
                
                if (!shape || length <= 0 || basePrice <= 0) {
                    fbResult.hide();
                    return;
                }
                
                // Получаем данные выбранной формы
                const shapeData = window.falsebalkShapesData ? window.falsebalkShapesData[shape] : null;
                
                if (!shapeData) {
                    return;
                }
                
                const area = (shapeData.perimeter / 1000) * length;
                let totalPrice = area * basePrice;
                
                // Добавляем покраску
                let grandTotal = totalPrice;
                const paintingSelect = $('#painting_service_select');
                if (paintingSelect.length && paintingSelect.val()) {
                    const paintingPrice = parseFloat(paintingSelect.find('option:selected').data('price')) || 0;
                    if (paintingPrice > 0) {
                        grandTotal += area * paintingPrice;
                    }
                }
                
                // Обновляем отображение
                $('#fb_shape_display').text(shapeData.label);
                $('#fb_length_display').text(length.toFixed(1).replace('.', ','));
                $('#fb_total_area').text(area.toFixed(3).replace('.', ','));
                $('#fb_total_price').text(formatPrice(grandTotal));
                
                // Скрытые поля
                createHiddenField('custom_fb_shape', shape);
                createHiddenField('custom_fb_length', length);
                createHiddenField('custom_fb_area', area.toFixed(3));
                createHiddenField('custom_fb_price', totalPrice.toFixed(2));
                createHiddenField('custom_fb_grand_total', grandTotal.toFixed(2));
                
                fbResult.show();
                $('input.qty').val(1).prop('readonly', true);
            }
            
            fbShape.on('change', updateFalsebalkCalculator);
            fbLength.on('input change', updateFalsebalkCalculator);
            $(document).on('change', '#painting_service_select', updateFalsebalkCalculator);
        }
        
        // ========================================
        // УСЛУГИ ПОКРАСКИ
        // ========================================
        
        const paintingSelect = $('#painting_service_select');
        const paintingColors = $('#painting_colors');
        
        if (paintingSelect.length) {
            console.log('✓ Painting services found');
            
            paintingSelect.on('change', function() {
                const serviceKey = $(this).val();
                
                if (!serviceKey) {
                    paintingColors.hide();
                    return;
                }
                
                // Показываем цвета для выбранной услуги
                $('.paint-colors').hide();
                $('#colors_' + serviceKey).show();
                paintingColors.show();
                
                // Пересчитываем калькулятор
                // (обработчики уже подключены выше)
            });
        }
        
        console.log('✅ ParusWeb Calculator JavaScript initialized');
    });
    </script>
    <?php
}
