<?php
/**
 * Модуль: Отображение калькуляторов
 * Описание: Главный диспетчер калькуляторов - определяет тип и вызывает нужную функцию
 * Зависимости: category-helpers, product-calculations, все calculator-*.php
 * 
 * ВАЖНО: Это ЕДИНСТВЕННЫЙ модуль который использует add_action для калькуляторов
 * Все остальные модули в /modules/calculators/ содержат только функции отображения
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ГЛАВНАЯ ФУНКЦИЯ - Определяет и выводит нужный калькулятор
 * Это единственный add_action для калькуляторов во всем плагине
 */
add_action('woocommerce_before_add_to_cart_button', 'display_product_calculators', 5);
function display_product_calculators() {
    global $product;
    
    if (!$product) {
        return;
    }
    
    $product_id = $product->get_id();
    
    // Проверяем нужен ли калькулятор
    if (!function_exists('is_in_target_categories') || !is_in_target_categories($product_id)) {
        return;
    }
    
    $calculator_type = function_exists('get_calculator_type') ? get_calculator_type($product_id) : 'none';
    
    if ($calculator_type === 'none') {
        return;
    }
    
    // Получаем данные товара
    $base_price = $product->get_price();
    $title = $product->get_title();
    $area_data = function_exists('extract_area_with_qty') ? extract_area_with_qty($title, $product_id) : null;
    $multiplier = function_exists('get_final_multiplier') ? get_final_multiplier($product_id) : 1.0;
    
    // Применяем множитель к цене
    $price_with_multiplier = $base_price * $multiplier;
    
    // Выводим нужный калькулятор
    switch ($calculator_type) {
        case 'area':
            // Калькулятор площади (пиломатериалы, листовые)
            if (function_exists('display_area_calculator')) {
                display_area_calculator($product_id, $base_price);
            }
            break;
            
        case 'square_meter':
            // Калькулятор квадратных метров (столярные изделия)
            if (function_exists('display_square_meter_calculator')) {
                display_square_meter_calculator($product_id, $price_with_multiplier, $area_data);
            }
            break;
            
        case 'running_meter':
            // Калькулятор погонных метров
            if (function_exists('display_running_meter_calculator')) {
                display_running_meter_calculator($product_id, $price_with_multiplier);
            }
            break;
            
        case 'falsebalk':
            // Калькулятор фальшбалок
            if (function_exists('display_falsebalk_calculator')) {
                display_falsebalk_calculator($product_id, $price_with_multiplier);
            }
            break;
            
        case 'dimensions':
            // Калькулятор размеров (столярные изделия с множителем)
            if (function_exists('display_dimensions_calculator')) {
                display_dimensions_calculator($product_id, $price_with_multiplier, $area_data);
            }
            break;
    }
    
    // Добавляем блок услуг покраски если нужно
    if (function_exists('is_in_painting_categories') && is_in_painting_categories($product_id)) {
        display_painting_services($product_id);
    }
}

/**
 * Вывод блока услуг покраски
 */
function display_painting_services($product_id) {
    // Получаем доступные услуги покраски
    $services = [];
    
    if (function_exists('get_available_painting_services_by_material')) {
        $services = get_available_painting_services_by_material($product_id);
    }
    
    if (empty($services)) {
        return;
    }
    
    ?>
    <div id="painting-services-block" style="margin: 20px 0; padding: 20px; border: 2px solid #ddd; border-radius: 8px; background: #f9f9f9;">
        <h4 style="margin-top: 0;">Услуги покраски</h4>
        
        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Выберите услугу:</label>
            <select id="painting_service_select" name="painting_service_key"
                    style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px;">
                <option value="">Без покраски</option>
                <?php foreach ($services as $key => $service): ?>
                    <option value="<?php echo esc_attr($key); ?>"
                            data-price="<?php echo esc_attr($service['price']); ?>"
                            data-schemes='<?php echo esc_attr(json_encode($service['schemes'] ?? [])); ?>'>
                        <?php echo esc_html($service['name']); ?> 
                        (+<?php echo wc_price($service['price']); ?> за м²)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div id="painting_color_container" style="display: none; margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Выберите цвет:</label>
            <select id="painting_color_select" name="painting_color_id"
                    style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px;">
                <option value="">Выберите цвет...</option>
            </select>
        </div>
        
        <div id="painting_price_display" style="display: none; padding: 10px; background: #e8f4f8; border-radius: 4px; margin-top: 10px;">
            <strong>Стоимость покраски:</strong> <span id="painting_price_value">0 ₽</span>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        console.log('✓ Painting Services initialized');
        
        $('#painting_service_select').on('change', function() {
            const selectedOption = $(this).find('option:selected');
            const serviceKey = selectedOption.val();
            
            if (!serviceKey) {
                $('#painting_color_container').hide();
                $('#painting_price_display').hide();
                // Триггерим перерасчет калькулятора
                $(document).trigger('painting_service_changed');
                return;
            }
            
            // Получаем схемы цветов
            const schemes = selectedOption.data('schemes');
            
            if (schemes && schemes.length > 0) {
                $('#painting_color_select').html('<option value="">Выберите цвет...</option>');
                schemes.forEach(scheme => {
                    $('#painting_color_select').append(
                        `<option value="${scheme.id}">${scheme.name}</option>`
                    );
                });
                $('#painting_color_container').show();
            } else {
                $('#painting_color_container').hide();
            }
            
            // Триггерим перерасчет калькулятора
            $(document).trigger('painting_service_changed');
        });
        
        $('#painting_color_select').on('change', function() {
            // Триггерим перерасчет калькулятора
            $(document).trigger('painting_service_changed');
        });
    });
    </script>
    <?php
}
