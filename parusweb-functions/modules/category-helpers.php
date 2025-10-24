<?php
/**
 * Модуль: Функции категорий (КРИТИЧЕСКИЙ)
 * Описание: Проверка категорий и определение типов калькуляторов
 * Зависимости: нет
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Проверка принадлежности товара к целевым категориям
 * (Категории где используются калькуляторы)
 */
function is_in_target_categories($product_id) {
    $product_categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
    
    if (is_wp_error($product_categories) || empty($product_categories)) {
        return false;
    }
    
    // Целевые категории:
    // 87-93: Пиломатериалы
    // 190-191, 127, 94: Листовые материалы  
    // 265-271: Столярные изделия
    // 266: Фальшбалки
    $target_categories = array_merge(
        range(87, 93),
        [190, 191, 127, 94],
        range(265, 271)
    );
    
    foreach ($product_categories as $cat_id) {
        if (in_array($cat_id, $target_categories)) {
            return true;
        }
        
        // Проверяем родительские категории
        foreach ($target_categories as $target_cat_id) {
            if (cat_is_ancestor_of($target_cat_id, $cat_id)) {
                return true;
            }
        }
    }
    
    return false;
}

/**
 * Проверка категорий с расчетом за кв.м (столярные изделия)
 */
function is_square_meter_category($product_id) {
    $product_categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
    
    if (is_wp_error($product_categories) || empty($product_categories)) {
        return false;
    }
    
    $target_categories = [266, 268, 270];
    
    foreach ($product_categories as $cat_id) {
        if (in_array($cat_id, $target_categories)) {
            return true;
        }
        
        foreach ($target_categories as $target_cat_id) {
            if (cat_is_ancestor_of($target_cat_id, $cat_id)) {
                return true;
            }
        }
    }
    
    return false;
}

/**
 * Проверка категорий с расчетом за пог.м (столярные изделия)
 */
function is_running_meter_category($product_id) {
    $product_categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
    
    if (is_wp_error($product_categories) || empty($product_categories)) {
        return false;
    }
    
    $target_categories = [267, 271];
    
    foreach ($product_categories as $cat_id) {
        if (in_array($cat_id, $target_categories)) {
            return true;
        }
        
        foreach ($target_categories as $target_cat_id) {
            if (cat_is_ancestor_of($target_cat_id, $cat_id)) {
                return true;
            }
        }
    }
    
    return false;
}

/**
 * Проверка категорий для покраски
 */
function is_in_painting_categories($product_id) {
    $product_categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
    
    if (is_wp_error($product_categories) || empty($product_categories)) {
        return false;
    }
    
    // Категории с покраской:
    // 87-93: Пиломатериалы
    // 190-191, 127, 94: Листовые
    // 265-271: Столярные изделия
    $target_categories = array_merge(
        range(87, 93),
        [190, 191, 127, 94],
        range(265, 271)
    );
    
    foreach ($product_categories as $cat_id) {
        if (in_array($cat_id, $target_categories)) {
            return true;
        }
        
        foreach ($target_categories as $target_cat_id) {
            if (cat_is_ancestor_of($target_cat_id, $cat_id)) {
                return true;
            }
        }
    }
    
    return false;
}

/**
 * Проверка принадлежности к категории листовых материалов
 */
function is_leaf_category($product_id) {
    $leaf_parent_id = 190;
    $leaf_children = [191, 127, 94];
    $leaf_ids = array_merge([$leaf_parent_id], $leaf_children);
    
    return has_term($leaf_ids, 'product_cat', $product_id);
}

/**
 * Проверка принадлежности к категориям пиломатериалов
 */
function is_lumber_category($product_id) {
    $lumber_categories = range(87, 93);
    
    return has_term($lumber_categories, 'product_cat', $product_id);
}

/**
 * Проверка принадлежности товара к конкретной категории
 */
function product_in_category($product_id, $category_id) {
    $terms = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
    
    if (is_wp_error($terms) || empty($terms)) {
        return false;
    }
    
    if (in_array($category_id, $terms)) {
        return true;
    }
    
    foreach ($terms as $term_id) {
        if (term_is_ancestor_of($category_id, $term_id, 'product_cat')) {
            return true;
        }
    }
    
    return false;
}

/**
 * Определение типа калькулятора для товара
 * 
 * @return string Тип калькулятора: 
 *   'area' - калькулятор площади
 *   'dimensions' - калькулятор размеров
 *   'falsebalk' - калькулятор фальшбалок
 *   'running_meter' - калькулятор погонных метров
 *   'square_meter' - калькулятор квадратных метров
 *   'none' - калькулятор не нужен
 */
function get_calculator_type($product_id) {
    $title = get_the_title($product_id);
    
    // 1. Проверяем фальшбалки (категория 266)
    if (product_in_category($product_id, 266)) {
        $shapes_data = get_post_meta($product_id, '_falsebalk_shapes_data', true);
        if (is_array($shapes_data) && !empty($shapes_data)) {
            foreach ($shapes_data as $shape_info) {
                if (!empty($shape_info['enabled'])) {
                    return 'falsebalk';
                }
            }
        }
    }
    
    // 2. Проверяем наличие площади в названии (для пиломатериалов и листовых)
    if (function_exists('extract_area_with_qty')) {
        $area = extract_area_with_qty($title, $product_id);
        if ($area && $area > 0) {
            // Если есть площадь в названии - используем калькулятор площади
            return 'area';
        }
    }
    
    // 3. Проверяем категории квадратных метров
    if (is_square_meter_category($product_id)) {
        return 'square_meter';
    }
    
    // 4. Проверяем категории погонных метров
    if (is_running_meter_category($product_id)) {
        return 'running_meter';
    }
    
    // 5. Проверяем мета-поля продажи
    if (get_post_meta($product_id, '_sold_by_area', true) === 'yes') {
        return 'square_meter';
    }
    
    if (get_post_meta($product_id, '_sold_by_length', true) === 'yes') {
        return 'running_meter';
    }
    
    // 6. Проверяем наличие размеров в названии (столярка)
    if (preg_match('/\d+\s*[х×*]\s*\d+/', $title)) {
        return 'dimensions';
    }
    
    return 'none';
}

/**
 * Получение единицы измерения товара на основе категории
 */
function get_category_based_unit($product_id) {
    if (is_square_meter_category($product_id)) {
        return 'м²';
    }
    
    if (is_running_meter_category($product_id)) {
        return 'м.п.';
    }
    
    if (is_leaf_category($product_id)) {
        return 'лист';
    }
    
    if (is_lumber_category($product_id)) {
        return 'упаковка';
    }
    
    $custom_unit = get_post_meta($product_id, '_custom_unit', true);
    if ($custom_unit) {
        return $custom_unit;
    }
    
    return 'шт';
}

/**
 * Получение форм склонения единицы измерения
 * Возвращает массив [единственное, два-четыре, пять и более]
 */
function get_unit_declension_forms($product_id) {
    $unit = get_category_based_unit($product_id);
    
    $forms = array(
        'м²' => array('м²', 'м²', 'м²'),
        'м.п.' => array('м.п.', 'м.п.', 'м.п.'),
        'лист' => array('лист', 'листа', 'листов'),
        'упаковка' => array('упаковка', 'упаковки', 'упаковок'),
        'шт' => array('штука', 'штуки', 'штук'),
        'л' => array('литр', 'литра', 'литров'),
    );
    
    return isset($forms[$unit]) ? $forms[$unit] : array($unit, $unit, $unit);
}

/**
 * Склонение числительных (русский язык)
 * 
 * @param int $number Число
 * @param array $forms Массив форм [1, 2, 5]
 * @return string Правильная форма слова
 */
function get_russian_plural($number, $forms) {
    $cases = array(2, 0, 1, 1, 1, 2);
    return $forms[($number % 100 > 4 && $number % 100 < 20) ? 2 : $cases[min($number % 10, 5)]];
}
