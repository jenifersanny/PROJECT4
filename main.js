// PNG Heritage Garments Store - Main JavaScript

class PNGMarketplace {
    constructor() {
        this.apiBase = '/api';
        this.currentUser = null;
        this.cart = { items: [], total: 0, count: 0 };
        this.init();
    }

    async init() {
        await this.checkAuth();
        this.setupEventListeners();
        this.loadCartCount();
        
        // Initialize page-specific functionality
        const page = document.body.dataset.page;
        if (page) {
            this[`init${page.charAt(0).toUpperCase() + page.slice(1)}`]?.();
        }
    }

    // Authentication methods
    async checkAuth() {
        try {
            const response = await fetch(`${this.apiBase}/auth.php?action=user`);
            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    this.currentUser = data.user;
                    this.updateAuthUI();
                }
            }
        } catch (error) {
            console.error('Auth check failed:', error);
        }
    }

    async login(username, password) {
        try {
            const response = await fetch(`${this.apiBase}/auth.php?action=login`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username, password })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.currentUser = data.user;
                this.updateAuthUI();
                this.showAlert('Login successful!', 'success');
                
                // Redirect based on role
                if (data.user.role === 'admin') {
                    window.location.href = '/admin.html';
                } else {
                    window.location.href = '/home.html';
                }
            } else {
                this.showAlert(data.message || 'Login failed', 'error');
            }
        } catch (error) {
            this.showAlert('Login failed. Please try again.', 'error');
        }
    }

    async register(userData) {
        try {
            const response = await fetch(`${this.apiBase}/auth.php?action=register`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(userData)
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.currentUser = data.user;
                this.updateAuthUI();
                this.showAlert('Registration successful!', 'success');
                window.location.href = '/home.html';
            } else {
                this.showAlert(data.message || 'Registration failed', 'error');
            }
        } catch (error) {
            this.showAlert('Registration failed. Please try again.', 'error');
        }
    }

    async logout() {
        try {
            await fetch(`${this.apiBase}/auth.php?action=logout`, { method: 'POST' });
            this.currentUser = null;
            this.cart = { items: [], total: 0, count: 0 };
            this.updateAuthUI();
            window.location.href = '/index.html';
        } catch (error) {
            console.error('Logout failed:', error);
        }
    }

    updateAuthUI() {
        const loginBtn = document.getElementById('loginBtn');
        const logoutBtn = document.getElementById('logoutBtn');
        const userInfo = document.getElementById('userInfo');
        
        if (this.currentUser) {
            if (loginBtn) loginBtn.style.display = 'none';
            if (logoutBtn) logoutBtn.style.display = 'inline-block';
            if (userInfo) {
                userInfo.textContent = `Welcome, ${this.currentUser.full_name}`;
                userInfo.style.display = 'inline';
            }
        } else {
            if (loginBtn) loginBtn.style.display = 'inline-block';
            if (logoutBtn) logoutBtn.style.display = 'none';
            if (userInfo) userInfo.style.display = 'none';
        }
    }

    // Product methods
    async getProducts(filters = {}) {
        try {
            const params = new URLSearchParams(filters);
            const response = await fetch(`${this.apiBase}/products.php?${params}`);
            return await response.json();
        } catch (error) {
            console.error('Failed to fetch products:', error);
            return [];
        }
    }

    async getProduct(id) {
        try {
            const response = await fetch(`${this.apiBase}/products.php?id=${id}`);
            return await response.json();
        } catch (error) {
            console.error('Failed to fetch product:', error);
            return null;
        }
    }

    async getCategories() {
        try {
            const response = await fetch(`${this.apiBase}/categories.php`);
            return await response.json();
        } catch (error) {
            console.error('Failed to fetch categories:', error);
            return [];
        }
    }

    // Cart methods
    async getCart() {
        if (!this.currentUser) {
            return this.getLocalCart();
        }
        
        try {
            const response = await fetch(`${this.apiBase}/cart.php`);
            if (response.ok) {
                this.cart = await response.json();
                this.updateCartUI();
                return this.cart;
            }
        } catch (error) {
            console.error('Failed to fetch cart:', error);
        }
        return { items: [], total: 0, count: 0 };
    }

    async addToCart(productId, quantity = 1, size = null, color = null) {
        if (!this.currentUser) {
            return this.addToLocalCart(productId, quantity, size, color);
        }
        
        try {
            const response = await fetch(`${this.apiBase}/cart.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    product_id: productId,
                    quantity: quantity,
                    size: size,
                    color: color
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                await this.getCart();
                this.showAlert('Item added to cart!', 'success');
                return true;
            } else {
                this.showAlert(data.message || 'Failed to add item to cart', 'error');
                return false;
            }
        } catch (error) {
            this.showAlert('Failed to add item to cart', 'error');
            return false;
        }
    }

    // Local storage cart methods (for non-authenticated users)
    getLocalCart() {
        const cart = localStorage.getItem('png_cart');
        return cart ? JSON.parse(cart) : { items: [], total: 0, count: 0 };
    }

    async addToLocalCart(productId, quantity, size, color) {
        const product = await this.getProduct(productId);
        if (!product) return false;
        
        let cart = this.getLocalCart();
        const existingItem = cart.items.find(item => 
            item.product_id == productId && 
            item.size === size && 
            item.color === color
        );
        
        if (existingItem) {
            existingItem.quantity += quantity;
        } else {
            cart.items.push({
                id: Date.now(),
                product_id: productId,
                name: product.name,
                price: product.price,
                image_url: product.image_url,
                quantity: quantity,
                size: size,
                color: color
            });
        }
        
        this.updateLocalCartTotals(cart);
        localStorage.setItem('png_cart', JSON.stringify(cart));
        this.cart = cart;
        this.updateCartUI();
        this.showAlert('Item added to cart!', 'success');
        return true;
    }

    updateLocalCartTotals(cart) {
        cart.total = cart.items.reduce((sum, item) => sum + (item.price * item.quantity), 0);
        cart.count = cart.items.reduce((sum, item) => sum + item.quantity, 0);
    }

    // UI methods
    setupEventListeners() {
        // Auth forms
        const loginForm = document.getElementById('loginForm');
        if (loginForm) {
            loginForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const formData = new FormData(loginForm);
                this.login(formData.get('username'), formData.get('password'));
            });
        }

        const registerForm = document.getElementById('registerForm');
        if (registerForm) {
            registerForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const formData = new FormData(registerForm);
                this.register({
                    username: formData.get('username'),
                    email: formData.get('email'),
                    password: formData.get('password'),
                    full_name: formData.get('full_name')
                });
            });
        }

        // Logout button
        const logoutBtn = document.getElementById('logoutBtn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.logout();
            });
        }

        // Search form
        const searchForm = document.getElementById('searchForm');
        if (searchForm) {
            searchForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const query = document.getElementById('searchInput').value;
                this.searchProducts(query);
            });
        }
    }

    async loadCartCount() {
        await this.getCart();
    }

    updateCartUI() {
        const cartCount = document.getElementById('cartCount');
        if (cartCount) {
            cartCount.textContent = this.cart.count || 0;
            cartCount.style.display = this.cart.count > 0 ? 'flex' : 'none';
        }
    }

    showAlert(message, type = 'info') {
        const alertContainer = document.getElementById('alertContainer') || this.createAlertContainer();
        
        const alert = document.createElement('div');
        alert.className = `alert alert-${type}`;
        alert.textContent = message;
        
        alertContainer.appendChild(alert);
        
        setTimeout(() => {
            alert.remove();
        }, 5000);
    }

    createAlertContainer() {
        const container = document.createElement('div');
        container.id = 'alertContainer';
        container.style.position = 'fixed';
        container.style.top = '20px';
        container.style.right = '20px';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
        return container;
    }

    // Page-specific initialization methods
    async initHome() {
        await this.loadFeaturedProducts();
    }

    async initShop() {
        await this.loadCategories();
        await this.loadProducts();
        this.setupShopFilters();
    }

    async initProduct() {
        const productId = this.getProductIdFromUrl();
        if (productId) {
            await this.loadProductDetail(productId);
        }
    }

    async loadFeaturedProducts() {
        const container = document.getElementById('featuredProducts');
        if (!container) return;
        
        const products = await this.getProducts({ featured: 'true' });
        container.innerHTML = products.map(product => this.renderProductCard(product)).join('');
    }

    async loadProducts(filters = {}) {
        const container = document.getElementById('productsGrid');
        if (!container) return;
        
        container.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
        
        const products = await this.getProducts(filters);
        container.innerHTML = products.map(product => this.renderProductCard(product)).join('');
    }

    async loadCategories() {
        const container = document.getElementById('categoriesFilter');
        if (!container) return;
        
        const categories = await this.getCategories();
        const categoryButtons = categories.map(category => 
            `<button class="btn btn-secondary category-filter" data-category="${category.id}">
                ${category.name}
            </button>`
        ).join('');
        
        container.innerHTML = `
            <button class="btn btn-primary category-filter active" data-category="">All Categories</button>
            ${categoryButtons}
        `;
        
        // Add event listeners
        container.querySelectorAll('.category-filter').forEach(btn => {
            btn.addEventListener('click', (e) => {
                container.querySelectorAll('.category-filter').forEach(b => b.classList.remove('active'));
                e.target.classList.add('active');
                
                const categoryId = e.target.dataset.category;
                this.loadProducts(categoryId ? { category_id: categoryId } : {});
            });
        });
    }

    renderProductCard(product) {
        return `
            <div class="product-card">
                <img src="${product.image_url || '/images/Placeholder.jpg'}" 
                     alt="${product.name}" class="product-image">
                <div class="product-info">
                    <h3 class="product-title">${product.name}</h3>
                    <p class="product-description">${product.description?.substring(0, 100)}...</p>
                    <div class="product-price">$${parseFloat(product.price).toFixed(2)}</div>
                    <div class="product-actions">
                        <a href="/product.html?id=${product.id}" class="btn btn-secondary">View Details</a>
                        <button onclick="marketplace.addToCart(${product.id})" class="btn btn-primary">Add to Cart</button>
                    </div>
                </div>
            </div>
        `;
    }

    getProductIdFromUrl() {
        const params = new URLSearchParams(window.location.search);
        return params.get('id');
    }

    async searchProducts(query) {
        if (query.trim()) {
            await this.loadProducts({ search: query });
        } else {
            await this.loadProducts();
        }
    }
}

// Initialize the marketplace when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.marketplace = new PNGMarketplace();
});