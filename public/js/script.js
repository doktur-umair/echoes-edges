

document.addEventListener('DOMContentLoaded', function() {

    const searchForm = document.getElementById('search-filter-form');
    
    // This entire block will only run if the main search form exists on the page
    if (searchForm) {
        const mainSearchInput = document.getElementById('main-search-input');
        const searchSuggestions = document.getElementById('search-suggestions');
        const filterToggleBtn = document.getElementById('filter-toggle-btn');
        const filterSection = document.getElementById('filter-section');
        let searchDebounceTimer;

        // --- Autocomplete for the main keyword search bar ---
        mainSearchInput.addEventListener('input', () => {
            clearTimeout(searchDebounceTimer);
            const term = mainSearchInput.value.trim();

            if (term.length < 2) {
                searchSuggestions.innerHTML = '';
                return;
            }

            searchDebounceTimer = setTimeout(() => {
                fetch(`/blog_app/api/search_posts.php?term=${encodeURIComponent(term)}`)
                    .then(response => response.json())
                    .then(data => {
                        let suggestionsHTML = '<ul>';
                        data.forEach(title => {
                            suggestionsHTML += `<li data-value="${title}">${title}</li>`;
                        });
                        suggestionsHTML += '</ul>';
                        searchSuggestions.innerHTML = suggestionsHTML;
                    });
            }, 300); // Debounce for 300ms
        });

        // --- Handle clicking on a search suggestion ---
        searchSuggestions.addEventListener('click', (e) => {
            if (e.target.tagName === 'LI') {
                mainSearchInput.value = e.target.dataset.value;
                searchSuggestions.innerHTML = '';
                searchForm.submit(); // Automatically submit the form
            }
        });
        
        // --- Logic for the filter button to show/hide the tag section ---
        filterToggleBtn.addEventListener('click', () => {
            filterSection.classList.toggle('hidden');
        });

        // --- Logic for the interactive "Fancy Tag Filter" ---
        const tagFilterContainer = document.getElementById('tag-filter-container');
        const tagFilterInput = document.getElementById('tag-filter-input');
        const tagSuggestions = document.getElementById('tag-suggestions');
        const hiddenTagsContainer = document.getElementById('hidden-tags-container');
        let filterTags = []; // Array to hold selected tag objects: {id, name}
        let tagDebounceTimer;
        
        // --- Function to render filter tag bubbles and hidden inputs ---
        const renderFilterTags = () => {
            // Clear all VISIBLE tag bubbles
            tagFilterContainer.querySelectorAll('.tag').forEach(t => t.remove());
            
            //  Clear all HIDDEN input fields
            hiddenTagsContainer.innerHTML = '';
            
            //  Re-create tags and inputs from our `filterTags` array
            filterTags.forEach(tag => {
                // Create and add the visual tag bubble
                const tagEl = document.createElement('div');
                tagEl.className = 'tag';
                tagEl.innerHTML = `<span>${tag.name}</span><span class="tag-close">&times;</span>`;
                
                tagEl.querySelector('.tag-close').addEventListener('click', () => {
                    filterTags = filterTags.filter(t => t.id !== tag.id);
                    renderFilterTags(); // Re-render everything after removal
                });
                
                // Add the bubble before the input field
                tagFilterContainer.insertBefore(tagEl, tagFilterInput);
                
                // Create and add the corresponding hidden input for the form
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'tags[]';
                hiddenInput.value = tag.id;
                hiddenTagsContainer.appendChild(hiddenInput);
            });
        };

        // --- Autocomplete for the tag filter input ---
        tagFilterInput.addEventListener('input', () => {
             clearTimeout(tagDebounceTimer);
             const term = tagFilterInput.value.trim();
             
             if (term.length > 0) {
                 tagDebounceTimer = setTimeout(() => {
                    fetch(`/blog_app/api/get_hashtags.php?term=${encodeURIComponent(term)}`)
                     .then(response => response.json())
                     .then(data => {
                         let suggestionsHTML = '<ul>';
                         data.forEach(tag => {
                             suggestionsHTML += `<li data-id="${tag.id}" data-name="${tag.name}">#${tag.name}</li>`;
                         });
                         suggestionsHTML += '</ul>';
                         tagSuggestions.innerHTML = suggestionsHTML;
                     });
                 }, 300);
             } else {
                 tagSuggestions.innerHTML = '';
             }
        });

        // --- Handle click on a tag suggestion in the filter ---
        tagSuggestions.addEventListener('click', (e) => {
            if (e.target.tagName === 'LI') {
                const tagId = parseInt(e.target.dataset.id);
                const tagName = e.target.dataset.name;
                
                // Add tag only if it's not already in our selected list
                if (!filterTags.some(t => t.id === tagId)) {
                    filterTags.push({ id: tagId, name: tagName });
                    renderFilterTags(); // Update the UI
                }
                
                tagFilterInput.value = ''; // Clear the input
                tagSuggestions.innerHTML = ''; // Hide suggestions
            }
        });
    }


    // tag editor
    const postTagEditorContainer = document.getElementById('tag-container');
    
    // This entire block will only run if the tag editor's main container exists
    if (postTagEditorContainer) {
        
        const textInput = document.getElementById('hashtags-input');
        const hiddenInput = document.getElementById('hidden-hashtags-input');
        const suggestionsBox = document.getElementById('suggestions-box');
        
        let tags = [];
        let debounceTimer;


        const renderEditorTags = () => {
            postTagEditorContainer.querySelectorAll('.tag').forEach(tagEl => tagEl.remove());
            tags.slice().reverse().forEach(tag => {
                const tagEl = createEditorTagElement(tag);
                postTagEditorContainer.insertBefore(tagEl, textInput);
            });
            hiddenInput.value = tags.join(',');
        };

        const createEditorTagElement = (label) => {
            const div = document.createElement('div');
            div.setAttribute('class', 'tag');
            div.innerHTML = `<span>${label}</span><span class="tag-close">&times;</span>`;
            div.querySelector('.tag-close').addEventListener('click', () => {
                tags = tags.filter(tag => tag !== label);
                renderEditorTags();
            });
            return div;
        };
        
        const addEditorTag = (newTag) => {
            const cleanedTag = newTag.trim().replace(/,/g, '');
            if (cleanedTag.length > 1 && !tags.includes(cleanedTag)) {
                tags.push(cleanedTag);
                renderEditorTags();
            }
            textInput.value = '';
            suggestionsBox.innerHTML = '';
        };

        // Initialize the editor with existing tags (for edit_post.php)
        if (hiddenInput.value) {
            const initialTags = hiddenInput.value.split(',').map(tag => tag.trim()).filter(Boolean);
            initialTags.forEach(tag => addEditorTag(tag));
        }

        // Event listeners for the editor
        textInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ',') {
                e.preventDefault();
                addEditorTag(textInput.value);
            }
        });

        textInput.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            const term = textInput.value.trim();
            if (term.length > 0) {
                 debounceTimer = setTimeout(() => {
                    fetch(`/blog_app/api/get_hashtags.php?term=${encodeURIComponent(term)}`)
                    .then(response => response.json())
                    .then(data => {
                        let suggestionsHTML = '<ul>';
                        data.forEach(tag => {
                             suggestionsHTML += `<li>#${tag.name}</li>`; // Only need name here
                        });
                        suggestionsHTML += '</ul>';
                        suggestionsBox.innerHTML = suggestionsHTML;
                    });
                 }, 300);
            } else {
                 suggestionsBox.innerHTML = '';
            }
        });
        
        suggestionsBox.addEventListener('click', (e) => {
            if (e.target.tagName === 'LI') {
                addEditorTag(e.target.textContent.replace('#', ''));
            }
        });
    }

    //hide ssuggestion when click outside
    document.addEventListener('click', (e) => {
        // Hide main search suggestions
        if (!e.target.closest('.search-input-wrapper')) {
            const searchSuggestions = document.getElementById('search-suggestions');
            if (searchSuggestions) searchSuggestions.innerHTML = '';
        }
        
        // Hide tag filter suggestions
        if (!e.target.closest('.tags-group')) {
            const tagSuggestions = document.getElementById('tag-suggestions');
            if (tagSuggestions) tagSuggestions.innerHTML = '';
        }

        // Hide tag editor suggestions
        const postTagEditorSuggestions = document.getElementById('suggestions-box');
        if (postTagEditorSuggestions && !e.target.closest('.form-group')) {
            postTagEditorSuggestions.innerHTML = '';
        }
    });
});