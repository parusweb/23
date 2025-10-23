<?php
/**
 * Модуль: Paint Schemes
 * Описание: Выбор схем покраски и цветов для товаров WooCommerce
 * Зависимости: frontend-calculators (для блока painting-services-block)
 */

if (!defined('ABSPATH')) {
    exit;
}

// === ДИАГНОСТИКА: Проверка загрузки модуля ===
error_log('=== PM Paint Schemes Module: LOADED ===');

// === Функция очистки имени файла цвета ===
if (!function_exists('pm_clean_color_filename')) {
    function pm_clean_color_filename($filename) {
        // Убираем расширение
        $filename = preg_replace('/\.(jpg|jpeg|png|webp|gif)$/i', '', $filename);
        
        // Убираем суффиксы типа -180, -1, -kopiya, _180 и т.д.
        $filename = preg_replace('/[-_](180|kopiya|copy|1)$/i', '', $filename);
        
        // Шаблоны для извлечения только кода цвета
        $patterns = [
            '/^img[_-]?(\d+)[-_].*$/i' => '$1',
            '/^(\d+)[-_]\d+$/i' => '$1',
            '/^[a-z]+[_-]?[a-z]*[_-]?(\d+)[-_]\d*$/i' => '$1',
            '/^([a-z]+)_dlya_pokraski[_-](\d+)$/i' => '$1_$2',
            '/^([a-z]+[_-]\d+[a-z0-9]+)[-_]\d+$/i' => '$1'
        ];
        
        foreach ($patterns as $pattern => $replacement) {
            if (preg_match($pattern, $filename)) {
                $filename = preg_replace($pattern, $replacement, $filename);
                break;
            }
        }
        
        // Финальная очистка
        $filename = preg_replace('/[-_]+/', '_', $filename);
        $filename = trim($filename, '-_');
        
        return $filename;
    }
}

// === Функция получения схем покраски товара ===
if (!function_exists('pm_get_product_paint_schemes')) {
    function pm_get_product_paint_schemes($product_id) {
        error_log('=== PM Paint Schemes DEBUG START for product #' . $product_id . ' ===');
        
        // 1. Проверяем индивидуальные схемы товара
        $schemes = get_field('custom_schemes', $product_id);
        if (!empty($schemes)) {
            error_log('✓ Found PRODUCT schemes: ' . count($schemes) . ' schemes');
            error_log('=== PM Paint Schemes DEBUG END ===');
            return $schemes;
        }
        error_log('✗ No product-level schemes found');

        // 2. Получаем ВСЕ категории товара
        $terms = get_the_terms($product_id, 'product_cat');
        
        if (!$terms || is_wp_error($terms)) {
            error_log('✗ No categories found');
            error_log('=== PM Paint Schemes DEBUG END ===');
            return [];
        }
        
        error_log('Product categories: ' . count($terms) . ' categories');
        foreach ($terms as $term) {
            error_log('  - Category #' . $term->term_id . ': "' . $term->name . '"');
        }
        
        // 3. Сортируем категории - СНАЧАЛА дочерние (с parent), ПОТОМ родительские
        usort($terms, function($a, $b) {
            if ($a->parent > 0 && $b->parent == 0) return -1;
            if ($b->parent > 0 && $a->parent == 0) return 1;
            return $b->term_id - $a->term_id;
        });
        
        // 4. Проверяем категории в новом порядке
        foreach ($terms as $term) {
            error_log('→ Checking category #' . $term->term_id . ': "' . $term->name . '"');
            
            // Проверяем поле 'schemes'
            $schemes = get_field('schemes', 'product_cat_' . $term->term_id);
            if (!empty($schemes)) {
                error_log('✓ Found schemes in category #' . $term->term_id . ': ' . count($schemes) . ' schemes');
                error_log('=== PM Paint Schemes DEBUG END ===');
                return $schemes;
            }
            
            // Проверяем поле 'custom_schemes'
            $schemes = get_field('custom_schemes', 'product_cat_' . $term->term_id);
            if (!empty($schemes)) {
                error_log('✓ Found custom_schemes in category #' . $term->term_id . ': ' . count($schemes) . ' schemes');
                error_log('=== PM Paint Schemes DEBUG END ===');
                return $schemes;
            }
        }
        
        // 5. Проверяем родительские категории
        foreach ($terms as $term) {
            $parent_id = $term->parent;
            while ($parent_id) {
                $parent_term = get_term($parent_id, 'product_cat');
                error_log('  → Checking PARENT category #' . $parent_id . ': "' . $parent_term->name . '"');
                
                $schemes = get_field('schemes', 'product_cat_' . $parent_id);
                if (!empty($schemes)) {
                    error_log('✓ Found schemes in PARENT category #' . $parent_id . ': ' . count($schemes) . ' schemes');
                    error_log('=== PM Paint Schemes DEBUG END ===');
                    return $schemes;
                }
                
                $schemes = get_field('custom_schemes', 'product_cat_' . $parent_id);
                if (!empty($schemes)) {
                    error_log('✓ Found custom_schemes in PARENT category #' . $parent_id . ': ' . count($schemes) . ' schemes');
                    error_log('=== PM Paint Schemes DEBUG END ===');
                    return $schemes;
                }
                
                $parent_id = $parent_term->parent;
            }
        }

        error_log('✗ NO SCHEMES FOUND for product #' . $product_id);
        error_log('=== PM Paint Schemes DEBUG END ===');
        return [];
    }
}

// === Добавление JavaScript для выбора схем/цветов ===
add_action('wp_footer', function() {
    if (!is_product()) {
        error_log('PM Paint Schemes: Not a product page, skipping');
        return;
    }
    
    global $product;
    
    error_log('PM Paint Schemes: wp_footer hook triggered for product #' . $product->get_id());
    
    // Проверяем, нужно ли показывать выбор цветов
    $product_id = $product->get_id();
    $can_show_colors = false;
    
    // Получаем категории товара для диагностики
    $product_categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
    error_log('PM Paint Schemes: Product categories: ' . print_r($product_categories, true));
    
    if (function_exists('is_in_painting_categories')) {
        $can_show_colors = is_in_painting_categories($product_id);
        error_log('PM Paint Schemes: is_in_painting_categories() returned: ' . ($can_show_colors ? 'TRUE' : 'FALSE'));
    } elseif (function_exists('is_in_target_categories')) {
        $can_show_colors = is_in_target_categories($product_id);
        error_log('PM Paint Schemes: is_in_target_categories() returned: ' . ($can_show_colors ? 'TRUE' : 'FALSE'));
    } else {
        error_log('PM Paint Schemes: Using fallback category check');
        // Fallback: проверяем категории напрямую
        if (!is_wp_error($product_categories) && !empty($product_categories)) {
            $target_categories = array_merge(
                range(87, 93),      // пиломатериалы
                [190, 191, 127, 94], // листовые материалы
                range(265, 271)      // столярные изделия
            );
            error_log('PM Paint Schemes: Target categories: ' . print_r($target_categories, true));
            
            foreach ($product_categories as $cat_id) {
                if (in_array($cat_id, $target_categories)) {
                    $can_show_colors = true;
                    error_log('PM Paint Schemes: Found matching category: ' . $cat_id);
                    break;
                }
            }
        }
    }
    
    if (!$can_show_colors) {
        error_log('PM Paint Schemes: Product not in target categories, exiting');
        return;
    }

    error_log('PM Paint Schemes: Product IS in target categories, proceeding...');

    $schemes = pm_get_product_paint_schemes($product->get_id());
    if (!is_array($schemes)) {
        error_log('PM Paint Schemes: Schemes is not an array, converting');
        $schemes = [];
    }
    
    error_log('PM Paint Schemes: Raw schemes count: ' . count($schemes));
    
    // Фильтруем схемы: убираем те, у которых нет имени или цветов
    $schemes = array_filter($schemes, function($scheme) {
        $has_name = !empty($scheme['scheme_name']);
        $has_colors = !empty($scheme['scheme_colors']) && is_array($scheme['scheme_colors']);
        
        if (!$has_name) {
            error_log('PM Paint Schemes: Skipping scheme with empty name');
        }
        if (!$has_colors) {
            error_log('PM Paint Schemes: Skipping scheme "' . ($scheme['scheme_name'] ?? 'EMPTY') . '" - no colors');
        }
        
        return $has_name && $has_colors;
    });
    
    if (empty($schemes)) {
        error_log('PM Paint Schemes: No valid schemes after filtering, exiting');
        return;
    }
    
    error_log('PM Paint Schemes: Using ' . count($schemes) . ' valid schemes');
    error_log('PM Paint Schemes: Outputting JavaScript...');
    
    ?>
    <script>
    console.log('=== PM Paint Schemes Script START ===');
    console.log('Current URL:', window.location.href);
    console.log('Product ID:', <?php echo $product->get_id(); ?>);
    
    (function() {
        'use strict';
        
        console.log('PM Paint Schemes: IIFE started');
        
        let checkAttempts = 0;
        const maxAttempts = 50; // 10 секунд (50 * 200ms)
        
        // Ждем инициализации калькулятора
        const checkForPaintingBlock = setInterval(function() {
            checkAttempts++;
            
            // Проверяем разные возможные контейнеры
            const paintingBlock = document.getElementById('painting-services-block');
            const calcResultContainer = document.querySelector('.calc-result-container');
            const customCalcBlock = document.getElementById('custom-calc-block');
            
            console.log(`PM Paint Schemes: Attempt ${checkAttempts}/${maxAttempts}`);
            console.log('  - painting-services-block:', !!paintingBlock);
            console.log('  - calc-result-container:', !!calcResultContainer);
            console.log('  - custom-calc-block:', !!customCalcBlock);
            
            if (paintingBlock) {
                console.log('PM Paint Schemes: ✓ painting-services-block FOUND!');
                clearInterval(checkForPaintingBlock);
                initPaintSchemes(paintingBlock);
                return;
            }
            
            // Альтернатива: если есть контейнер калькулятора, но нет блока услуг
            if ((calcResultContainer || customCalcBlock) && checkAttempts > 10) {
                console.log('PM Paint Schemes: Found calc container but no painting block');
                console.log('PM Paint Schemes: Creating painting-services-block manually...');
                
                const container = calcResultContainer || customCalcBlock;
                const manualPaintingBlock = document.createElement('div');
                manualPaintingBlock.id = 'painting-services-block';
                manualPaintingBlock.innerHTML = '<br><h4>Услуги покраски</h4><div id="painting_service_select_placeholder"></div>';
                container.appendChild(manualPaintingBlock);
                
                clearInterval(checkForPaintingBlock);
                initPaintSchemes(manualPaintingBlock);
                return;
            }
            
            if (checkAttempts >= maxAttempts) {
                console.error('PM Paint Schemes: ✗ painting-services-block NOT FOUND after ' + maxAttempts + ' attempts');
                console.error('PM Paint Schemes: Available elements:', {
                    forms: document.querySelectorAll('form').length,
                    cartForms: document.querySelectorAll('form.cart').length,
                    calcBlocks: document.querySelectorAll('[id*="calc"]').length
                });
                clearInterval(checkForPaintingBlock);
            }
        }, 200);
        
        function initPaintSchemes(paintingBlock) {
            console.log('PM Paint Schemes: initPaintSchemes() called');
            
            const schemes = <?php echo json_encode($schemes); ?>;
            console.log('PM Paint Schemes: Schemes loaded:', schemes.length, 'schemes');
            console.log('PM Paint Schemes: Schemes data:', schemes);
            
            // Создаем блок для схем и цветов
            let schemesBlock = document.getElementById('paint-schemes-block');
            if (schemesBlock) {
                console.log('PM Paint Schemes: Removing existing paint-schemes-block');
                schemesBlock.remove();
            }
            
            console.log('PM Paint Schemes: Creating HTML...');
            const html = createSchemesHTML();
            paintingBlock.insertAdjacentHTML('beforeend', html);
            schemesBlock = document.getElementById('paint-schemes-block');
            
            if (!schemesBlock) {
                console.error('PM Paint Schemes: ✗ Failed to create paint-schemes-block!');
                return;
            }
            
            console.log('PM Paint Schemes: ✓ paint-schemes-block created successfully');
            
            // Показываем блок сразу для диагностики
            schemesBlock.style.display = 'block';
            console.log('PM Paint Schemes: Block set to display:block for testing');
            
            // Инициализируем содержимое
            console.log('PM Paint Schemes: Updating scheme options...');
            updateSchemeOptions(schemes);
            
            console.log('PM Paint Schemes: Updating color blocks...');
            updateColorBlocks(schemes);
            
            console.log('PM Paint Schemes: Setting up event handlers...');
            setupEventHandlers(schemes);
            
            console.log('PM Paint Schemes: ✓ Initialization complete!');
            console.log('=== PM Paint Schemes Script END ===');
        }
        
        function createSchemesHTML() {
            return `
            <div id="paint-schemes-block" style="display:none; margin-top:20px; border-top:1px solid #ddd; padding-top:15px;">
                <h4>Цвет покраски</h4>
                
                <!-- Селектор схемы -->
                <div id="scheme-selector" style="margin-bottom:15px; display:block;">
                    <label style="display: block; margin-bottom: 10px;">
                        Схема покраски:
                        <select id="pm_scheme_select" style="width:100%; padding:5px; background:#fff; margin-top:5px;">
                            <option value="">Выберите схему</option>
                        </select>
                    </label>
                </div>
                
                <!-- Блок предпросмотра выбранного цвета -->
                <div id="color-preview-container" style="display:none; margin-bottom:20px; padding:20px; background:#f5f5f5; border-radius:8px; border:2px solid #4CAF50;">
                    <div style="position:relative; margin-bottom:15px; text-align:center;">
                        <div style="position:relative; display:inline-block;">
                            <div style="border:3px solid #4CAF50; border-radius:8px; padding:5px; background:#fff; box-shadow:0 4px 12px rgba(76, 175, 80, 0.3);">
                                <img id="color-preview-image" src="" alt="" style="height:200px; object-fit:cover; display:block; border-radius:4px;">
                            </div>
                            <!-- Зеленая галочка -->
                            <div style="position:absolute; top:-10px; right:-10px; width:50px; height:50px; background:#4CAF50; border-radius:50%; display:flex; align-items:center; justify-content:center; box-shadow:0 4px 8px rgba(0,0,0,0.2); border:3px solid #fff;">
                                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="20 6 9 17 4 12"></polyline>
                                </svg>
                            </div>
                        </div>
                    </div>
                    <p id="color-preview-scheme" style="margin:10px 0; font-weight:600; font-size:16px; color:#333; text-align:center;"></p>
                    <p id="color-preview-code" style="margin:10px 0; font-size:18px; font-weight:700; color:#4CAF50; text-align:center;"></p>
                    
                    <!-- Кнопка "Выбрать другой цвет" -->
                    <div style="text-align:center; margin-top:15px;">
                        <button type="button" id="change-color-btn" style="padding:10px 20px; background:#fff; border:2px solid #0073aa; color:#0073aa; border-radius:5px; cursor:pointer; font-weight:600; transition:all 0.3s;">
                            Выбрать другой цвет
                        </button>
                    </div>
                </div>
                
                <!-- Блоки с палитрой цветов -->
                <div id="color-blocks-container"></div>
            </div>
            <style>
                .pm-color-option {
                    transition: transform 0.2s ease, box-shadow 0.2s ease;
                }
                .pm-color-option:hover {
                    transform: scale(1.1);
                    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
                    z-index: 10;
                    position: relative;
                }
                .pm-color-option img {
                    transition: all 0.2s ease;
                }
                .pm-color-option input:checked + img {
                    border: 3px solid #4CAF50;
                    box-shadow: 0 0 0 2px #fff, 0 0 0 4px #4CAF50;
                }
                #change-color-btn:hover {
                    background:#0073aa;
                    color:#fff;
                    transform:scale(1.05);
                }
            </style>
            `;
        }
        
        function normalizeSchemeSlug(scheme) {
            let slug = scheme.scheme_slug;
            if (!slug || slug === 'undefined' || slug === '') {
                slug = scheme.scheme_name.toLowerCase()
                    .trim()
                    .replace(/[^\wа-яё0-9\s]/gi, '')
                    .replace(/\s+/g, '-')
                    .replace(/-+/g, '-')
                    .replace(/^-|-$/g, '');
            }
            return slug;
        }
        
        function cleanColorFilename(filename) {
            filename = filename.replace(/\.(jpg|jpeg|png|webp|gif)$/i, '');
            filename = filename.replace(/[-_](180|kopiya|copy|1)$/i, '');
            
            const patterns = [
                [/^img[_-]?(\d+)[-_].*$/i, '$1'],
                [/^(\d+)[-_]\d+$/i, '$1'],
                [/^[a-z]+[_-]?[a-z]*[_-]?(\d+)[-_]\d*$/i, '$1'],
                [/^([a-z]+)_dlya_pokraski[_-](\d+)$/i, '$1_$2'],
                [/^([a-z]+[_-]\d+[a-z0-9]+)[-_]\d+$/i, '$1']
            ];
            
            for (let [pattern, replacement] of patterns) {
                if (pattern.test(filename)) {
                    filename = filename.replace(pattern, replacement);
                    break;
                }
            }
            
            filename = filename.replace(/[-_]+/g, '_').replace(/^[-_]|[-_]$/g, '');
            return filename;
        }
        
        function updateSchemeOptions(schemes) {
            console.log('updateSchemeOptions: called with', schemes.length, 'schemes');
            const select = document.getElementById('pm_scheme_select');
            if (!select) {
                console.error('updateSchemeOptions: pm_scheme_select not found!');
                return;
            }
            
            select.innerHTML = '<option value="">Выберите схему</option>';
            
            schemes.forEach((s, index) => {
                if (!s.scheme_name || s.scheme_name.trim() === '') {
                    console.warn('updateSchemeOptions: Skipping scheme', index, 'with empty name');
                    return;
                }
                
                const slug = normalizeSchemeSlug(s);
                const opt = document.createElement('option');
                opt.value = slug;
                opt.textContent = s.scheme_name;
                opt.dataset.name = s.scheme_name;
                select.appendChild(opt);
                
                console.log('updateSchemeOptions: Added option:', s.scheme_name, '(slug:', slug + ')');
            });
            
            console.log('updateSchemeOptions: ✓ Complete, total options:', select.options.length);
        }
        
        function updateColorBlocks(schemes) {
            console.log('updateColorBlocks: called with', schemes.length, 'schemes');
            const container = document.getElementById('color-blocks-container');
            if (!container) {
                console.error('updateColorBlocks: color-blocks-container not found!');
                return;
            }
            
            container.innerHTML = '';
            
            schemes.forEach((scheme, index) => {
                if (!scheme.scheme_name || scheme.scheme_name.trim() === '') {
                    console.warn('updateColorBlocks: Skipping scheme', index, 'with empty name');
                    return;
                }
                
                const slug = normalizeSchemeSlug(scheme);
                const name = scheme.scheme_name;
                const colors = scheme.scheme_colors || [];
                
                console.log('updateColorBlocks: Processing scheme:', name, 'with', colors.length, 'colors');
                
                if (!colors.length) {
                    console.warn('updateColorBlocks: No colors for scheme:', name);
                    return;
                }
                
                let html = `<div class="pm-paint-colors" data-scheme="${slug}" style="display:none; margin-bottom:15px;">
                                <p><strong>${name}: выберите цвет</strong></p>
                                <div style="display:flex; flex-wrap:wrap; gap:10px;">`;
                
                colors.forEach((c, colorIndex) => {
                    const rawFilename = c.url.split('/').pop();
                    const cleanFilename = cleanColorFilename(rawFilename);
                    const value = `${name} — ${cleanFilename}`;
                    
                    console.log('updateColorBlocks: Adding color', colorIndex, ':', cleanFilename);
                    
                    html += `<label class="pm-color-option" style="cursor:pointer; border:2px solid transparent; border-radius:6px; overflow:hidden;">
                                <input type="radio" name="pm_selected_color" 
                                       value="${value}" 
                                       data-filename="${cleanFilename}" 
                                       data-image="${c.url}"
                                       data-scheme="${name}" 
                                       style="display:none;" required>
                                <img src="${c.url}" alt="${cleanFilename}" title="${cleanFilename}" style="width:50px;height:50px;object-fit:cover; display:block;">
                            </label>`;
                });
                
                html += '</div></div>';
                container.insertAdjacentHTML('beforeend', html);
                console.log('updateColorBlocks: ✓ Added block for scheme:', name);
            });
            
            console.log('updateColorBlocks: ✓ Complete, total blocks:', container.children.length);
        }
        
        function findMatchingScheme(schemes, serviceName) {
            if (!serviceName) return null;
            
            const validSchemes = schemes.filter(s => s.scheme_name && s.scheme_name.trim() !== '');
            if (validSchemes.length === 0) return null;
            
            // Очищаем название услуги
            let cleanServiceName = serviceName
                .replace(/\s*\(\+.*?\)$/g, '')
                .replace(/\s*\+.*$/g, '')
                .toLowerCase()
                .replace(/[^\wа-яё\s]/gi, '')
                .trim();
            
            if (!cleanServiceName) return null;
            
            // Убираем специфичные слова
            const wordsToRemove = ['столешницы', 'столешницу', 'изделия', 'изделие', 'доски', 'доску', 'материала', 'наличника', 'наличник'];
            let simplifiedServiceName = cleanServiceName;
            wordsToRemove.forEach(word => {
                const regex = new RegExp('\\b' + word + '\\b', 'gi');
                simplifiedServiceName = simplifiedServiceName.replace(regex, '').replace(/\s+/g, ' ').trim();
            });
            
            // 1. Точное совпадение
            let found = validSchemes.find(s => {
                let cleanSchemeName = s.scheme_name.toLowerCase().replace(/[^\wа-яё\s]/gi, '').trim();
                return cleanSchemeName === cleanServiceName;
            });
            if (found) return found;
            
            // 2. Совпадение упрощенного названия
            found = validSchemes.find(s => {
                let cleanSchemeName = s.scheme_name.toLowerCase().replace(/[^\wа-яё\s]/gi, '').trim();
                return cleanSchemeName === simplifiedServiceName;
            });
            if (found) return found;
            
            // 3. Совпадение без слова "покраска"
            let serviceWithoutPokraska = simplifiedServiceName.replace(/покр?аска\s*/gi, '').trim();
            found = validSchemes.find(s => {
                let schemeWithoutPokraska = s.scheme_name.toLowerCase()
                    .replace(/[^\wа-яё\s]/gi, '')
                    .replace(/покр?аска\s*/gi, '')
                    .trim();
                return schemeWithoutPokraska === serviceWithoutPokraska;
            });
            if (found) return found;
            
            // 4. Совпадение по ключевым словам
            const stopWords = ['покраска', 'покрасить', 'для', 'по', 'в', 'на', 'с', 'из'];
            const serviceWords = simplifiedServiceName.split(/\s+/).filter(word => 
                word.length > 2 && !stopWords.includes(word)
            ).slice(0, 3);
            
            if (serviceWords.length > 0) {
                found = validSchemes.find(s => {
                    let cleanSchemeName = s.scheme_name.toLowerCase().replace(/[^\wа-яё\s]/gi, '').trim();
                    const schemeWords = cleanSchemeName.split(/\s+/).filter(word => 
                        word.length > 2 && !stopWords.includes(word)
                    );
                    
                    let matchCount = 0;
                    for (let word of serviceWords) {
                        if (schemeWords.some(sw => sw.includes(word) || word.includes(sw))) {
                            matchCount++;
                        }
                    }
                    
                    return matchCount >= Math.min(2, serviceWords.length);
                });
                if (found) return found;
            }
            
            return null;
        }
        
        function showColors(slug) {
            console.log('showColors: called with slug:', slug);
            
            let foundBlock = false;
            document.querySelectorAll('.pm-paint-colors').forEach(block => {
                if (block.dataset.scheme === slug) {
                    block.style.display = 'block';
                    foundBlock = true;
                    console.log('showColors: ✓ Showing block for scheme:', slug);
                } else {
                    block.style.display = 'none';
                }
            });
            
            if (!foundBlock) {
                console.error('showColors: ✗ No block found for slug:', slug);
            }
            
            // Сбрасываем выбор цвета
            document.querySelectorAll('input[name="pm_selected_color"]').forEach(radio => {
                radio.checked = false;
            });
            
            // Скрываем превью и показываем палитру
            const previewContainer = document.getElementById('color-preview-container');
            const colorBlocksContainer = document.getElementById('color-blocks-container');
            
            if (previewContainer) previewContainer.style.display = 'none';
            if (colorBlocksContainer) colorBlocksContainer.style.display = 'block';
            
            // Сбрасываем скрытые поля
            const form = document.querySelector('form.cart');
            if (form) {
                ['pm_selected_color_image', 'pm_selected_color_filename'].forEach(name => {
                    const field = form.querySelector(`input[name="${name}"]`);
                    if (field) field.value = '';
                });
            }
        }
        
        function setupEventHandlers(allSchemes) {
            console.log('setupEventHandlers: called');
            
            const serviceSelect = document.getElementById('painting_service_select');
            const schemesBlock = document.getElementById('paint-schemes-block');
            const schemeSelect = document.getElementById('pm_scheme_select');
            const form = document.querySelector('form.cart');
            
            console.log('setupEventHandlers: Elements found:', {
                serviceSelect: !!serviceSelect,
                schemesBlock: !!schemesBlock,
                schemeSelect: !!schemeSelect,
                form: !!form
            });
            
            // Добавляем скрытые поля в форму
            if (form && !form.querySelector('#pm_selected_scheme_name')) {
                console.log('setupEventHandlers: Adding hidden fields to form');
                form.insertAdjacentHTML('beforeend', `
                    <input type="hidden" id="pm_selected_scheme_name" name="pm_selected_scheme_name" value="">
                    <input type="hidden" id="pm_selected_scheme_slug" name="pm_selected_scheme_slug" value="">
                    <input type="hidden" id="pm_selected_color_image" name="pm_selected_color_image" value="">
                    <input type="hidden" id="pm_selected_color_filename" name="pm_selected_color_filename" value="">
                `);
            }
            
            // Обработчик выбора услуги покраски
            if (serviceSelect) {
                console.log('setupEventHandlers: Attaching change handler to painting_service_select');
                serviceSelect.addEventListener('change', function() {
                    console.log('serviceSelect: change event fired');
                    const selectedOption = this.options[this.selectedIndex];
                    const serviceName = selectedOption.text;
                    
                    console.log('serviceSelect: Selected service:', serviceName);
                    
                    if (this.value && serviceName) {
                        const matchingScheme = findMatchingScheme(allSchemes, serviceName);
                        
                        if (matchingScheme) {
                            console.log('serviceSelect: Found matching scheme:', matchingScheme.scheme_name);
                            const schemeSlug = normalizeSchemeSlug(matchingScheme);
                            
                            // Скрываем селектор схем
                            document.getElementById('scheme-selector').style.display = 'none';
                            
                            // Пересоздаем блоки цветов только для этой схемы
                            updateColorBlocks([matchingScheme]);
                            
                            // Устанавливаем скрытые поля
                            document.getElementById('pm_selected_scheme_name').value = matchingScheme.scheme_name;
                            document.getElementById('pm_selected_scheme_slug').value = schemeSlug;
                            
                            // Показываем блок схем и цвета
                            schemesBlock.style.display = 'block';
                            setTimeout(() => showColors(schemeSlug), 100);
                        } else {
                            console.log('serviceSelect: No matching scheme, showing selector');
                            // Несколько схем - показываем селектор
                            document.getElementById('scheme-selector').style.display = 'block';
                            updateSchemeOptions(allSchemes);
                            updateColorBlocks(allSchemes);
                            schemesBlock.style.display = 'block';
                        }
                    } else {
                        console.log('serviceSelect: No service selected, hiding schemes block');
                        schemesBlock.style.display = 'none';
                    }
                });
            } else {
                console.warn('setupEventHandlers: painting_service_select not found!');
            }
            
            // Обработчик выбора схемы из селекта
            if (schemeSelect) {
                console.log('setupEventHandlers: Attaching change handler to pm_scheme_select');
                schemeSelect.addEventListener('change', function() {
                    console.log('schemeSelect: change event fired');
                    const selectedSlug = this.value;
                    const selectedName = this.options[this.selectedIndex].dataset.name || '';
                    
                    console.log('schemeSelect: Selected:', selectedName, '(slug:', selectedSlug + ')');
                    
                    if (selectedSlug) {
                        document.getElementById('pm_selected_scheme_name').value = selectedName;
                        document.getElementById('pm_selected_scheme_slug').value = selectedSlug;
                        showColors(selectedSlug);
                    }
                });
            }
            
            // Обработчик выбора цвета
            document.addEventListener('change', function(e) {
                if (e.target.name === 'pm_selected_color') {
                    console.log('colorSelect: Color selected');
                    
                    // Показываем превью выбранного цвета
                    const selectedImg = e.target.nextElementSibling;
                    if (selectedImg) {
                        const previewContainer = document.getElementById('color-preview-container');
                        const previewImage = document.getElementById('color-preview-image');
                        const previewScheme = document.getElementById('color-preview-scheme');
                        const previewCode = document.getElementById('color-preview-code');
                        
                        previewImage.src = selectedImg.src;
                        previewImage.alt = e.target.dataset.filename;
                        previewScheme.textContent = e.target.dataset.scheme;
                        previewCode.textContent = 'Код: ' + e.target.dataset.filename;
                        
                        // Показываем превью, скрываем палитру
                        previewContainer.style.display = 'block';
                        document.getElementById('color-blocks-container').style.display = 'none';
                        
                        // Сохраняем данные в скрытые поля
                        document.getElementById('pm_selected_color_image').value = selectedImg.src;
                        document.getElementById('pm_selected_color_filename').value = e.target.dataset.filename;
                        
                        console.log('colorSelect: ✓ Preview shown, data saved');
                    }
                }
            });
            
            // Обработчик кнопки "Выбрать другой цвет"
            document.addEventListener('click', function(e) {
                if (e.target.id === 'change-color-btn') {
                    console.log('changeColorBtn: clicked');
                    
                    const previewContainer = document.getElementById('color-preview-container');
                    const colorBlocksContainer = document.getElementById('color-blocks-container');
                    
                    previewContainer.style.display = 'none';
                    colorBlocksContainer.style.display = 'block';
                    
                    // Сбрасываем выбор
                    document.querySelectorAll('input[name="pm_selected_color"]').forEach(radio => {
                        radio.checked = false;
                    });
                    
                    document.getElementById('pm_selected_color_image').value = '';
                    document.getElementById('pm_selected_color_filename').value = '';
                    
                    console.log('changeColorBtn: ✓ Selection reset');
                }
            });
            
            console.log('setupEventHandlers: ✓ All handlers attached');
        }
    })();
    </script>
    <?php
}, 30);

// === Передача данных в корзину ===
add_filter('woocommerce_add_cart_item_data', 'pm_add_paint_data_to_cart', 10, 3);
function pm_add_paint_data_to_cart($cart_item_data, $product_id, $variation_id) {
    // Обрабатываем имя файла цвета с фильтрацией
    if (!empty($_POST['pm_selected_color_filename'])) {
        $cleaned_filename = pm_clean_color_filename($_POST['pm_selected_color_filename']);
        $cart_item_data['pm_selected_color'] = $cleaned_filename;
        $cart_item_data['pm_selected_color_filename'] = $cleaned_filename;
    } elseif (!empty($_POST['pm_selected_color'])) {
        $color_value = sanitize_text_field($_POST['pm_selected_color']);
        if (strpos($color_value, ' — ') !== false) {
            $parts = explode(' — ', $color_value);
            $cleaned_filename = pm_clean_color_filename(end($parts));
        } else {
            $cleaned_filename = pm_clean_color_filename($color_value);
        }
        $cart_item_data['pm_selected_color'] = $cleaned_filename;
        $cart_item_data['pm_selected_color_filename'] = $cleaned_filename;
    }
    
    if (!empty($_POST['pm_selected_scheme_name'])) {
        $cart_item_data['pm_selected_scheme_name'] = sanitize_text_field($_POST['pm_selected_scheme_name']);
    }
    
    if (!empty($_POST['pm_selected_scheme_slug'])) {
        $cart_item_data['pm_selected_scheme_slug'] = sanitize_text_field($_POST['pm_selected_scheme_slug']);
    }
    
    if (!empty($_POST['pm_selected_color_image'])) {
        $cart_item_data['pm_selected_color_image'] = esc_url_raw($_POST['pm_selected_color_image']);
    }
    
    return $cart_item_data;
}

// === Сохранение в заказ ===
add_action('woocommerce_checkout_create_order_line_item', 'pm_add_paint_data_to_order', 10, 4);
function pm_add_paint_data_to_order($item, $cart_item_key, $values, $order) {
    if (!empty($values['pm_selected_scheme_name'])) {
        $scheme_display = $values['pm_selected_scheme_name'];
        
        if (!empty($values['pm_selected_color'])) {
            $scheme_display .= ' — ' . $values['pm_selected_color'];
        }
        
        $item->add_meta_data('Схема покраски', $scheme_display, true);
    }
    
    if (!empty($values['pm_selected_color_image'])) {
        $item->add_meta_data('_pm_color_image_url', $values['pm_selected_color_image'], true);
    }
    
    if (!empty($values['pm_selected_color'])) {
        $item->add_meta_data('Код цвета', $values['pm_selected_color'], true);
    }
}

// === Отображение изображения цвета в заказе ===
add_filter('woocommerce_order_item_display_meta_key', 'pm_rename_color_meta_key', 10, 3);
function pm_rename_color_meta_key($display_key, $meta, $item) {
    if ($meta->key === '_pm_color_image_url') {
        return 'Образец цвета';
    }
    return $display_key;
}

add_filter('woocommerce_order_item_display_meta_value', 'pm_display_color_image_in_order', 10, 3);
function pm_display_color_image_in_order($display_value, $meta, $item) {
    if ($meta->key === '_pm_color_image_url') {
        $image_url = $meta->value;
        return '<img src="' . esc_url($image_url) . '" style="width:60px; height:60px; object-fit:cover; border:2px solid #ddd; border-radius:4px; display:block; margin-top:5px;">';
    }
    return $display_value;
}