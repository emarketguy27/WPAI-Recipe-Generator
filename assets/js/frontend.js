jQuery(document).ready(function($) {
    let initialFormState = '';

    // Store initial form state on load
    function storeFormState() {
        initialFormState = $('#wpai-recipe-generator-form').serialize();
    }
    storeFormState();

    // Form change detection
    $('#wpai-recipe-generator-form').on('input change', function() {
        const currentState = $(this).serialize();
        $('.rg-submit').prop('disabled', currentState === initialFormState);
    });

    // Reset form handler
    $('#reset-form-btn').on('click', function() {
        $('#wpai-recipe-generator-form')[0].reset();
        $('.dietary-option').removeClass('checked');
        
        storeFormState();
        
        $('#wpai-recipe-generator-form .rg-submit').prop('disabled', false);
        
        $(this).addClass('clicked');
        setTimeout(() => {
            $(this).removeClass('clicked');
        }, 300);
        
        $(this).html('<span class="dashicons dashicons-update"></span> Reset Form');
    });

    // Recipe Generation Form Submit Handler
    $('#wpai-recipe-generator-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $results = $('#recipe-results');
        var $loading = $('.rg-loading');
        var $submitBtn = $form.find('#generate-btn');
        var $saveBtn = $('#save-recipe-btn');

        $saveBtn.removeClass('saved');
        $saveBtn.text('Save to Favorites');
        $saveBtn.prop('disabled', false);
        
        // Show loading state
        $submitBtn.prop('disabled', true);
        $submitBtn.addClass('clicked');
        $loading.show();

        const messages = [
            "Analyzing your ingredients...",
            "Checking your Dietary Requirements...",
            "Consulting our chef AI...",
            "Verifying Nutrition...",
            "Finalizing the perfect recipe..."
        ];
        
        let currentMessage = 0;
        $('.loading-text').text(messages[currentMessage]);
        
        // Cycle through messages every 3 seconds
        const messageInterval = setInterval(() => {
            currentMessage = (currentMessage + 1) % messages.length;
            $('.loading-text').fadeOut(400, function() {
                $(this).text(messages[currentMessage]).fadeIn(400);
            });
        }, 3000);
        
        // Prepare data
        var formData = {
            action: 'wp_ai_recipe_generator_generate_recipe',
            _wpnonce: $form.find('input[name="_wpnonce"]').val(),
            servings: $form.find('#rg-servings').val(),
            include: $form.find('#rg-include').val(),
            exclude: $form.find('#rg-exclude').val(),
            dietary: $form.find('input[name="dietary[]"]:checked').map(function() {
                return this.value;
            }).get()
        };
        
        // Make the request
        $.post(recipeGeneratorFrontendVars.ajaxurl, formData, function(response) {
            if (response.success) {
                handleGenerateSuccess();

                storeFormState();
                $('#generate-btn').prop('disabled', true);

                clearInterval(messageInterval);
                $results.html(response.data.html).addClass('show').show();
                $('#recipe-actions').show();
                
                // Smooth scroll to results with proper positioning
                setTimeout(() => {
                    $('html, body').stop().animate({
                    scrollTop: $results.offset().top - 140
                }, 800);
                }, 500);
                
            } else {
                $results.html(
                    '<div class="error">' + 
                    (response.data || recipeGeneratorFrontendVars.errorOccurred) + 
                    '</div>'
                ).addClass('show').show();
            }
        }).fail(function() {
            $results.html(
                '<div class="error">' + recipeGeneratorFrontendVars.errorOccurred + '</div>'
            ).addClass('show').show();
        }).always(function() {
            $loading.hide();
            $submitBtn.prop('disabled', false);
            setTimeout(() => {
               $submitBtn.removeClass('clicked');
            }, "1000");
        });
    });

    // Form selection boxes interactions
    document.querySelectorAll('.dietary-option input[type="checkbox"]').forEach(checkbox => {
    // Initialize checked state
    if (checkbox.checked) {
        checkbox.closest('.dietary-option').classList.add('checked');
    }
    
    // Add change event
    checkbox.addEventListener('change', function() {
        const optionCard = this.closest('.dietary-option');
        
        // Toggle checked class
        optionCard.classList.toggle('checked', this.checked);
        
        // Add ripple effect
        if (this.checked) {
        const ripple = document.createElement('span');
        ripple.classList.add('ripple');
        optionCard.appendChild(ripple);
        setTimeout(() => ripple.remove(), 600);
        }
    });
    
    // Keyboard accessibility
    checkbox.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        checkbox.checked = !checkbox.checked;
        checkbox.dispatchEvent(new Event('change'));
        }
    });
    });

    function handleGenerateSuccess() {
        const generateBtn = document.querySelector('.rg-submit');
        if (generateBtn) {
        generateBtn.classList.add('clicked');
        setTimeout(() => generateBtn.classList.remove('clicked'), 400);
        }
    }

    // Button Interactions (applied to all buttons)
    document.querySelectorAll('.rg-submit, #save-recipe-btn, .view-recipe-btn, .modal-action:not(.platform-share-btn)').forEach(button => {
        // Add ripple effect on click
        button.addEventListener('click', function(e) {
            // Create ripple
            const ripple = document.createElement('span');
            ripple.classList.add('button-ripple');
            
            // Position ripple at click location
            const rect = button.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            ripple.style.width = ripple.style.height = `${size}px`;
            ripple.style.left = `${e.clientX - rect.left - size/2}px`;
            ripple.style.top = `${e.clientY - rect.top - size/2}px`;
            
            button.appendChild(ripple);
            
            // Remove ripple after animation
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
        
        // Keyboard support
        button.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.click();
            }
        });
    });

    $(document).on('click', '#save-recipe-btn', function() {
        const $btn = $(this);
        const $status = $('#save-status');
        const recipeHtml = $('#recipe-results').html();
        
        // Generate unique ID from recipe content
        const recipeId = generateRecipeId(recipeHtml);
        
        // Extract dietary tags from the generated recipe HTML
        const dietaryTags = [];
        $('#recipe-results .dietary-tag').each(function() {
            dietaryTags.push($(this).text().trim());
        });

        $btn.prop('disabled', true);
        $status.text('Saving...').hide().fadeIn(400);
        
        $.post(recipeGeneratorFrontendVars.ajaxurl, {
            action: 'save_ai_recipe_to_favorites',
            recipe_id: recipeId,
            recipe_html: recipeHtml,
            wpai_dietary_tags: dietaryTags, // Add the tags to the AJAX request
            _wpnonce: recipeGeneratorFrontendVars.nonce
        }, function(response) {
            if (response.success) {                
                setTimeout(() => {
                    $btn.addClass('saved');
                    $status.text('Recipe saved to your favorites!').hide().fadeIn(700); 
                }, 500);
                
                // Update the count if on saved recipes page
                const $countDisplay = $('.recipe-count');
                if ($countDisplay.length) {
                    const currentCount = parseInt($countDisplay.text().replace(/[()]/g, '')) || 0;
                    $countDisplay.text('(' + (currentCount + 1) + ')');
                }
            } else {
                $status.text('Error: ' + (response.data || 'Could not save recipe'));
                $btn.prop('disabled', false);
            }
        }).fail(function() {
            $status.text('You must be a logged-in member to save recipes');
            $btn.prop('disabled', false);
        });
    });

    // Unique ID creations for recipes being saved to user meta
    function generateRecipeId(html) {
        // Create hash from timestamp + random number + first 100 chars of recipe
        const timestamp = Date.now().toString(36);
        const random = Math.random().toString(36).substring(2, 8);
        const contentHash = String(html.length) + html.substring(0, 100).replace(/\s+/g, '');
        return 'recipe_' + timestamp + '_' + random + '_' + contentHash.length;
    }

    $(document).on('click', '.view-recipe-btn', function() {
        const $item = $(this).closest('.saved-recipe-item');
        const recipeId = $item.data('recipe-id');
        const $modal = $('#recipe-modal');
        const $modalContent = $('#modal-recipe-content');

        $modal.data('current-recipe-id', recipeId);

        $.post(recipeGeneratorFrontendVars.ajaxurl, {
            action: 'check_recipe_post',
            recipe_id: recipeId,
            _wpnonce: recipeGeneratorFrontendVars.nonce
        }, function(response) {
            const hasPost = response.data.has_post;
            const postUrl = response.data.post_url;

            const $viewPostBtn = $modal.find('.view-post-btn');
            if (hasPost && postUrl) {
                $viewPostBtn.show().attr('href', postUrl);
            } else {
                $viewPostBtn.hide().removeAttr('href');
            }
            
            // Store post status in modal data
            $modal.data('has-post', hasPost);
            $modal.data('post-url', postUrl);
            
            // Toggle platform share buttons
            $('.platform-share-btn').toggleClass('disabled', !hasPost);
        
            // Use the localized data
            if (recipeGeneratorFrontendVars.saved_recipes && 
                recipeGeneratorFrontendVars.saved_recipes[recipeId]) {
                $modalContent.html(recipeGeneratorFrontendVars.saved_recipes[recipeId].html);

                $modal.show();
                setTimeout(() => {
                    $modal.addClass('show');
                }, 10);
                
                // Smooth scroll to top of modal
                $modalContent.scrollTop(0);
            } else {
                console.error('Recipe not found:', recipeId);
                $modalContent.html('<p>Error: Recipe could not be loaded</p>');
                $modal.show();
                setTimeout(() => {
                    $modal.addClass('show');
                }, 10);
            }
        });
    });

    // Close modal
    $(document).on('click', '.close-modal, .recipe-modal', function(e) {
        // Only close if clicking directly on overlay or close button
        if ($(e.target).hasClass('recipe-modal') || $(e.target).hasClass('close-modal')) {
            closeModal();
        }
    });

    function closeModal() {
        const $modal = $('#recipe-modal');
        $modal.removeClass('show');
        setTimeout(() => {
            $modal.hide();
        }, 300);
    }

    // Escape key closes modal
    $(document).keyup(function(e) {
        if (e.key === "Escape") {
            closeModal();
        }
    });

    // Close when clicking outside modal
    $(document).on('click', function(e) {
        if ($(e.target).hasClass('recipe-modal')) {
            closeModal();
        }
    });

    // Close share modal
    $(document).on('click', '.close-share-modal, .share-modal', function(e) {
        if ($(e.target).hasClass('share-modal') || $(e.target).hasClass('close-share-modal')) {
            $('#share-options-modal').hide();
        }
    });

    // Platform-specific sharing
    $(document).on('click', '.share-btn', function(e) {
        e.preventDefault();
        const platform = $(this).data('platform');
        const recipeTitle = encodeURIComponent($('#modal-recipe-content h2').text() || 'Check out this recipe');
        const recipeUrl = encodeURIComponent(window.location.href.split('?')[0]);
        const recipeText = encodeURIComponent($('#modal-recipe-content').text().substring(0, 300) + '...');
        
        let shareUrl = '';
        
        switch(platform) {
            case 'facebook':
                shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${recipeUrl}`;
                break;
            case 'twitter':
                shareUrl = `https://twitter.com/intent/tweet?text=${recipeTitle}&url=${recipeUrl}`;
                break;
            case 'pinterest':
                // Note: Pinterest requires an image - using a placeholder
                const imageUrl = encodeURIComponent('https://via.placeholder.com/300x200?text=Recipe');
                shareUrl = `https://pinterest.com/pin/create/button/?url=${recipeUrl}&media=${imageUrl}&description=${recipeTitle}`;
                break;
            case 'reddit':
                shareUrl = `https://www.reddit.com/submit?url=${recipeUrl}&title=${recipeTitle}`;
                break;
        }        
        $('#share-options-modal').hide();
    });

    // Platform share handlers (only when enabled)
    $(document).on('click', '.platform-share-btn:not(.disabled)', function(e) {
        e.preventDefault();
        const platform = $(this).data('platform');
        const postUrl = $('#recipe-modal').data('post-url');
        
        switch(platform) {
            case 'facebook':
                window.open(`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(postUrl)}`, '_blank');
                break;
            case 'twitter':
                window.open(`https://twitter.com/intent/tweet?url=${encodeURIComponent(postUrl)}`, '_blank');
                break;
            case 'pinterest':
                window.open(`https://pinterest.com/pin/create/button/?url=${encodeURIComponent(postUrl)}`, '_blank');
                break;
            case 'reddit':
                window.open(`https://www.reddit.com/submit?url=${encodeURIComponent(postUrl)}`, '_blank');
                break;
            // Add other platforms
        }
    });

    $(document).on('click', '.delete-recipe', function() {
        if (!confirm('Are you sure you want to delete this recipe?')) {
            return;
        }
        
        const $modal = $('#recipe-modal');
        const recipeId = $modal.data('current-recipe-id');

        if (!recipeId) {
            alert('Error: Could not identify recipe to delete');
            return;
        }
        
        $.post(recipeGeneratorFrontendVars.ajaxurl, {
            action: 'delete_saved_recipe',
            recipe_id: recipeId,
            _wpnonce: recipeGeneratorFrontendVars.nonce
        }, function(response) {
            if (response.success) {
                // Close modal and remove from list
                $modal.hide();
                $(`.saved-recipe-item[data-recipe-id="${recipeId}"]`).remove();
                
                // Update count
                const $count = $('.recipe-count');
                if ($count.length) {
                    const current = parseInt($count.text().match(/\d+/)[0]) || 0;
                    $count.text(`(${current - 1})`);
                }
                
                // Show feedback if no recipes left
                if ($('.saved-recipe-item').length === 0) {
                    $('.saved-recipes-list').html('<p>You have no saved recipes.</p>');
                }
            } else {
                alert('Error: ' + (response.data || 'Could not delete recipe'));
            }
        });
    });
    
    $(document).on('click', '.print-recipe', function() {
        const printContent = $('#modal-recipe-content').html();
        const printWindow = window.open('', '', '');
        
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>${document.title}</title>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    h2 { color: #222; margin-bottom: 10px; }
                    .recipe-meta { display: flex; justify-content: space-between; margin-bottom: 0px; color: #666; }
                    .recipe-section { margin-bottom: 5px; }
                    ul, ol { padding-left: 20px; }
                    .dietary-tag { 
                        display: inline-block;
                        background: #e0f7fa;
                        padding: 2px 8px;
                        margin-right: 5px;
                        border: 1px solid;
                        border-radius: 12px;
                        font-size: 0.8em;
                    }
                    @page { margin: 1cm; }
                </style>
            </head>
            <body>
                ${printContent}
                <script>
                    window.onload = function() {
                        setTimeout(function() {
                            window.print();
                            window.close();
                        }, 200);
                    }
                </script>
            </body>
            </html>
        `);
        printWindow.document.close();
    });

    $(document).on('click', '.share-recipe', function() {
        if (navigator.share && /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
            // Mobile devices with share support
            const recipeTitle = $('#modal-recipe-content h2').text() || 'Check out this recipe';
            const recipeText = $('#modal-recipe-content').text().substring(0, 200) + '...';
            const shareUrl = window.location.href.split('?')[0];
            
            navigator.share({
                title: recipeTitle,
                text: recipeText,
                url: shareUrl
            }).catch(err => {
                console.log('Share failed:', err);
            });
        } else {
            // Desktop - show share options modal
            $('#share-options-modal').show();
        }
    });

    $(document).on('click', '.copy-recipe', function() {
        // Get the recipe content container PROPERLY
        const recipeContent = $('#modal-recipe-content .recipe-json-output').clone();
        
        // Extract all recipe components DIRECTLY from the HTML structure
        const title = recipeContent.find('h2').text().trim();
        const description = recipeContent.find('p.recipe-description').text().trim();
        
        // Get metadata - SERVINGS, PREP TIME, COOK TIME
        const servings = recipeContent.find('.meta-group:contains("Servings") p').text().replace('Servings:', '').trim();
        const prepTime = recipeContent.find('.meta-group:contains("Prep Time") p').text().replace('Prep Time:', '').trim();
        const cookTime = recipeContent.find('.meta-group:contains("Cook Time") p').text().replace('Cook Time:', '').trim();
        
        // Get dietary tags
        const dietaryTags = [];
        recipeContent.find('.dietary-tag').each(function() {
            dietaryTags.push($(this).text().trim());
        });
        
        // Get ingredients
        const ingredients = [];
        recipeContent.find('.recipe-ingredients li').each(function() {
            ingredients.push('• ' + $(this).text().trim());
        });
        
        // Get instructions
        const instructions = [];
        recipeContent.find('.recipe-instructions li').each(function(i) {
            instructions.push(`${i+1}. ${$(this).text().trim()}`);
        });
        
        // Get nutrition (if exists)
        const nutrition = [];
        recipeContent.find('.recipe-nutrition li').each(function() {
            nutrition.push($(this).text().trim());
        });
        
        // Build the final text output
        const recipeText = `
    ${title.toUpperCase()}
    ${description ? '\n' + description + '\n' : ''}

    Servings: ${servings} | Prep Time: ${prepTime} | Cook Time: ${cookTime}
    ${dietaryTags.length ? 'Dietary: ' + dietaryTags.join(', ') + '\n' : ''}

    INGREDIENTS:
    ${ingredients.join('\n')}

    INSTRUCTIONS:
    ${instructions.join('\n')}

    ${nutrition.length ? '\nNUTRITION:\n' + nutrition.join('\n') : ''}

    --- 
    Recipe from ${window.location.hostname}
    `.trim();

        // SIMPLE clipboard copy (no fancy functions needed)
        const textarea = document.createElement('textarea');
        textarea.value = recipeText;
        document.body.appendChild(textarea);
        textarea.select();
        
        try {
            document.execCommand('copy');
            const $button = $(this);
            const originalText = $button.html();
            $button.html('<span class="dashicons dashicons-yes"></span> Copied!');
            setTimeout(() => $button.html(originalText), 2000);
        } catch (err) {
            prompt('Press Ctrl+C to copy:', recipeText);
        } finally {
            document.body.removeChild(textarea);
        }
    });

    $(document).on('click', '.email-recipe', function(e) {
        e.preventDefault();
        
        // Get the recipe content container
        const $recipe = $('#modal-recipe-content .recipe-json-output');
        const staticSubjectPrefix = "Check out this recipe: ";
         
        
        // Extract all components (same as print/copy)
        const title = $recipe.find('h2').text().trim();
        const description = $recipe.find('p.recipe-description').text().trim();
        
        // Get metadata
        const metaItems = [];
        $recipe.find('.meta-group').each(function() {
            metaItems.push($(this).find('p').text().trim());
        });
        
        // Format ingredients
        const ingredients = [];
        $recipe.find('.recipe-ingredients li').each(function() {
            ingredients.push('• ' + $(this).text().trim());
        });
        
        // Format instructions
        const instructions = [];
        $recipe.find('.recipe-instructions li').each(function(i) {
            instructions.push(`${i+1}. ${$(this).text().trim()}`);
        });
        
        // Format nutrition if exists
        let nutrition = [];
        $recipe.find('.recipe-nutrition li').each(function() {
            nutrition.push($(this).text().trim());
        });
        
        // Build email body (same formatting as print)
        const emailBody = `
    ${title.toUpperCase()}
    ${description ? '\n' + description + '\n' : ''}

    ${metaItems.join(' | ')}
    ${nutrition.length ? '\nNUTRITION:\n' + nutrition.join('\n') : ''}

    INGREDIENTS:
    ${ingredients.join('\n')}

    INSTRUCTIONS:
    ${instructions.join('\n')}

    --- 
    Sent from ${window.location.hostname}
    `.trim();
        
        // Encode for mailto link
        const emailSubject = staticSubjectPrefix + title;
        const encodedBody = encodeURIComponent(emailBody);
        // const encodedSubject = encodeURIComponent(title);
        const encodedSubject = encodeURIComponent(emailSubject);
        
        // Open email client
        window.location.href = `mailto:?subject=${encodedSubject}&body=${encodedBody}`;
    });
});