let currentPage = 1;
let isLoading = false;
let hasMoreProducts = true;
let currentKategori = '';
let currentSearch = typeof searchQuery !== 'undefined' ? searchQuery : ''; // Safely get searchQuery from PHP

// DOM elements
const produkContainer = document.getElementById('produkContainer');
const produkLoader = document.getElementById('produkLoader');

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    // Get initial parameters from URL if available
    const urlParams = new URLSearchParams(window.location.search);
    currentSearch = urlParams.get('search') || currentSearch;
    currentKategori = urlParams.get('kategori') || '';
    
    // Set category filter if specified in URL
    if (currentKategori) {
        const categoryRadio = document.querySelector(`input[name="kategori"][value="${currentKategori}"]`);
        if (categoryRadio) {
            categoryRadio.checked = true;
        }
    }
    
    loadProducts();
    setupSearchAutocomplete();
    setupFilters();
    setupInfiniteScroll();
});

// Enhanced search autocomplete
function setupSearchAutocomplete() {
    const searchInput = document.querySelector('input[name="search"]');
    if (!searchInput) return;

    let searchTimeout;
    let autocompleteContainer;

    // Create autocomplete container
    const createAutocompleteContainer = () => {
        if (autocompleteContainer) return autocompleteContainer;
        
        autocompleteContainer = document.createElement('div');
        autocompleteContainer.className = 'search-autocomplete';
        autocompleteContainer.style.cssText = `
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            z-index: 1000;
            max-height: 300px;
            overflow-y: auto;
            display: none;
        `;
        
        searchInput.parentNode.style.position = 'relative';
        searchInput.parentNode.appendChild(autocompleteContainer);
        return autocompleteContainer;
    };

    // Enhanced search suggestions
    const fetchSuggestions = async (query) => {
        if (query.length < 2) {
            hideAutocomplete();
            return;
        }

        try {
            const response = await fetch(`search_suggestions.php?q=${encodeURIComponent(query)}`);
            
            if (!response.ok) {
                console.warn('Search suggestions failed:', response.status);
                hideAutocomplete();
                return;
            }
            
            const suggestions = await response.json();
            
            if (suggestions && suggestions.length > 0) {
                showSuggestions(suggestions, query);
            } else {
                hideAutocomplete();
            }
        } catch (error) {
            console.error('Error fetching suggestions:', error);
            hideAutocomplete();
        }
    };

    const showSuggestions = (suggestions, query) => {
        const container = createAutocompleteContainer();
        container.innerHTML = '';

        suggestions.forEach(suggestion => {
            const item = document.createElement('div');
            item.className = 'autocomplete-item';
            item.style.cssText = `
                padding: 12px 16px;
                cursor: pointer;
                border-bottom: 1px solid #eee;
                transition: background-color 0.2s;
            `;
            
            // Highlight matching text
            const highlightedText = suggestion.replace(
                new RegExp(`(${escapeRegExp(query)})`, 'gi'),
                '<strong style="color: var(--main-color);">$1</strong>'
            );
            
            item.innerHTML = `<i class="fa fa-search" style="color: #999; margin-right: 8px;"></i>${highlightedText}`;
            
            item.addEventListener('mouseenter', () => {
                item.style.backgroundColor = '#f5f5f5';
            });
            
            item.addEventListener('mouseleave', () => {
                item.style.backgroundColor = 'transparent';
            });
            
            item.addEventListener('click', () => {
                searchInput.value = suggestion;
                hideAutocomplete();
                performSearch(suggestion);
            });
            
            container.appendChild(item);
        });

        container.style.display = 'block';
    };

    const hideAutocomplete = () => {
        if (autocompleteContainer) {
            autocompleteContainer.style.display = 'none';
        }
    };

    // Event listeners
    searchInput.addEventListener('input', (e) => {
        clearTimeout(searchTimeout);
        const query = e.target.value.trim();
        
        searchTimeout = setTimeout(() => {
            fetchSuggestions(query);
        }, 300);
    });

    searchInput.addEventListener('focus', (e) => {
        if (e.target.value.length >= 2) {
            fetchSuggestions(e.target.value.trim());
        }
    });

    // Hide autocomplete when clicking outside
    document.addEventListener('click', (e) => {
        if (!searchInput.contains(e.target) && !autocompleteContainer?.contains(e.target)) {
            hideAutocomplete();
        }
    });

    // Handle form submission
    const searchForm = searchInput.closest('form');
    if (searchForm) {
        searchForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const query = searchInput.value.trim();
            hideAutocomplete();
            
            if (query) {
                // Update URL without page reload
                const newUrl = new URL(window.location);
                newUrl.searchParams.set('search', query);
                newUrl.searchParams.delete('page');
                window.history.pushState({}, '', newUrl);
                
                performSearch(query);
                saveSearchHistory(query);
            }
        });
    }
}

// Escape special regex characters
function escapeRegExp(string) {
    return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

// Perform search with enhanced parameters
function performSearch(query) {
    currentSearch = query;
    currentPage = 1;
    hasMoreProducts = true;
    produkContainer.innerHTML = '';
    loadProducts();
}

// Setup category filters
function setupFilters() {
    const kategoriFilters = document.querySelectorAll('.filter-kategori');
    
    kategoriFilters.forEach(filter => {
        filter.addEventListener('change', function() {
            if (this.checked) {
                currentKategori = this.value;
                currentPage = 1;
                hasMoreProducts = true;
                produkContainer.innerHTML = '';
                loadProducts();
                
                // Update URL
                const newUrl = new URL(window.location);
                if (currentKategori) {
                    newUrl.searchParams.set('kategori', currentKategori);
                } else {
                    newUrl.searchParams.delete('kategori');
                }
                newUrl.searchParams.delete('page');
                window.history.pushState({}, '', newUrl);
            }
        });
    });
}

// Enhanced infinite scroll
function setupInfiniteScroll() {
    let scrollTimeout;
    
    window.addEventListener('scroll', () => {
        clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(() => {
            if (shouldLoadMore()) {
                loadProducts();
            }
        }, 100);
    });
}

function shouldLoadMore() {
    if (isLoading || !hasMoreProducts) return false;
    
    const scrollPosition = window.scrollY + window.innerHeight;
    const documentHeight = document.documentElement.scrollHeight;
    const threshold = 200; // Load when 200px from bottom
    
    return scrollPosition >= documentHeight - threshold;
}

// Enhanced product loading with better error handling
async function loadProducts() {
    if (isLoading || !hasMoreProducts) return;
    
    isLoading = true;
    if (produkLoader) {
        produkLoader.style.display = 'block';
    }
    
    try {
        const params = new URLSearchParams({
            page: currentPage,
            kategori: currentKategori || '',
            search: currentSearch || ''
        });
        
        console.log('Loading products with params:', params.toString()); // Debug log
        
        const response = await fetch(`load_product.php?${params}`);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const html = await response.text();
        console.log('Response received, length:', html.length); // Debug log
        
        // Check if response contains pagination info
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = html;
        const paginationInfo = tempDiv.querySelector('#pagination-info');
        
        if (html.trim() === '' || html.includes('Tidak ada produk ditemukan')) {
            hasMoreProducts = false;
            
            if (currentPage === 1) {
                produkContainer.innerHTML = html;
            }
        } else {
            // Remove pagination info from display HTML
            if (paginationInfo) {
                paginationInfo.remove();
            }
            
            const displayHtml = tempDiv.innerHTML;
            
            if (currentPage === 1) {
                produkContainer.innerHTML = displayHtml;
            } else {
                produkContainer.insertAdjacentHTML('beforeend', displayHtml);
            }
            
            // Check if we have more products based on pagination info
            if (paginationInfo) {
                const totalProducts = parseInt(paginationInfo.dataset.totalProducts);
                const limit = parseInt(paginationInfo.dataset.limit);
                const loadedProducts = currentPage * limit;
                hasMoreProducts = loadedProducts < totalProducts;
            }
            
            currentPage++;
        }
        
        // Setup cart buttons for new products
        setupCartButtons();
        
    } catch (error) {
        console.error('Error loading products:', error);
        
        if (currentPage === 1) {
            produkContainer.innerHTML = `
                <div style="text-align:center;padding:40px;background:#fff;border-radius:8px;grid-column:1/-1;">
                    <i class="fa fa-exclamation-triangle" style="font-size:48px;color:#ff6b6b;margin-bottom:20px;"></i>
                    <h3 style="color:#666;margin-bottom:10px;">Terjadi kesalahan</h3>
                    <p style="color:#999;margin-bottom:20px;">Gagal memuat produk. Silakan coba lagi.</p>
                    <button onclick="location.reload()" style="padding:10px 20px;background:var(--main-color);color:white;border:none;border-radius:5px;cursor:pointer;">
                        Muat Ulang
                    </button>
                </div>
            `;
        }
    } finally {
        isLoading = false;
        if (produkLoader) {
            produkLoader.style.display = 'none';
        }
    }
}

// Setup cart functionality for dynamically loaded products
function setupCartButtons() {
    const cartButtons = document.querySelectorAll('.btnKeranjang:not([data-initialized])');
    
    cartButtons.forEach(button => {
        button.setAttribute('data-initialized', 'true');
        button.addEventListener('click', function() {
            const productId = this.getAttribute('data-id');
            addToCart(productId, this);
        });
    });
}

// Enhanced add to cart with visual feedback
async function addToCart(productId, button) {
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Menambah...';
    button.disabled = true;
    
    try {
        const formData = new FormData();
        formData.append('id_produk', productId);
        formData.append('action', 'add-to-cart');
        
        const response = await fetch('add-to-cart.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.text();
        
        if (result.trim() === 'success') {
            // Visual feedback
            button.innerHTML = '<i class="fa fa-check"></i> Ditambahkan!';
            button.style.background = '#28a745';
            
            // Update cart counter if exists
            updateCartDisplay();
            
            // Reset button after 2 seconds
            setTimeout(() => {
                button.innerHTML = originalText;
                button.style.background = '';
                button.disabled = false;
            }, 2000);
            
        } else {
            throw new Error(result);
        }
        
    } catch (error) {
        console.error('Error adding to cart:', error);
        button.innerHTML = '<i class="fa fa-exclamation"></i> Gagal';
        button.style.background = '#dc3545';
        
        setTimeout(() => {
            button.innerHTML = originalText;
            button.style.background = '';
            button.disabled = false;
        }, 2000);
    }
}

// Update cart display
function updateCartDisplay() {
    // This function should be implemented based on your cart system
    // You can fetch cart count and update the header cart icon
    fetchCartCount();
}

async function fetchCartCount() {
    try {
        const response = await fetch('get_cart_count.php');
        
        if (!response.ok) {
            console.warn('Cart count fetch failed:', response.status);
            return;
        }
        
        const data = await response.json();
        
        const countElements = document.querySelectorAll('.count_item, .count_item_cart');
        countElements.forEach(el => {
            el.textContent = data.count || '0';
        });
        
        const priceElements = document.querySelectorAll('.price_cart_Head, .price_cart_total');
        priceElements.forEach(el => {
            el.textContent = `Rp ${data.total || '0'}`;
        });
        
    } catch (error) {
        console.error('Error fetching cart count:', error);
    }
}

// Filter toggle for mobile
function open_close_filter() {
    const filter = document.querySelector('.filter');
    if (filter) {
        filter.classList.toggle('active');
    }
}

// Advanced search features
function initAdvancedSearch() {
    // Voice search if supported
    if ('webkitSpeechRecognition' in window) {
        addVoiceSearch();
    }
    
    // Search history
    loadSearchHistory();
}

function addVoiceSearch() {
    const searchInput = document.querySelector('input[name="search"]');
    if (!searchInput) return;
    
    const voiceButton = document.createElement('button');
    voiceButton.innerHTML = '<i class="fa fa-microphone"></i>';
    voiceButton.type = 'button';
    voiceButton.style.cssText = `
        position: absolute;
        right: 50px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: #666;
        cursor: pointer;
        padding: 5px;
    `;
    
    const searchContainer = searchInput.parentNode;
    searchContainer.style.position = 'relative';
    searchContainer.appendChild(voiceButton);
    
    const recognition = new webkitSpeechRecognition();
    recognition.lang = 'id-ID';
    recognition.continuous = false;
    recognition.interimResults = false;
    
    voiceButton.addEventListener('click', () => {
        recognition.start();
        voiceButton.innerHTML = '<i class="fa fa-microphone" style="color: red;"></i>';
    });
    
    recognition.onresult = (event) => {
        const transcript = event.results[0][0].transcript;
        searchInput.value = transcript;
        performSearch(transcript);
        voiceButton.innerHTML = '<i class="fa fa-microphone"></i>';
    };
    
    recognition.onerror = () => {
        voiceButton.innerHTML = '<i class="fa fa-microphone"></i>';
    };
}

function loadSearchHistory() {
    const searchHistory = JSON.parse(localStorage.getItem('searchHistory') || '[]');
    // Implement search history display if needed
}

function saveSearchHistory(query) {
    if (!query || query.length < 2) return;
    
    let history = JSON.parse(localStorage.getItem('searchHistory') || '[]');
    history = history.filter(item => item !== query); // Remove duplicates
    history.unshift(query); // Add to beginning
    history = history.slice(0, 10); // Keep only last 10 searches
    
    localStorage.setItem('searchHistory', JSON.stringify(history));
}

// Initialize advanced features when DOM is ready
document.addEventListener('DOMContentLoaded', initAdvancedSearch);