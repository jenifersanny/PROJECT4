-- PNG Heritage Garments Store Database Schema

CREATE DATABASE IF NOT EXISTS png_marketplace;
USE png_marketplace;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('customer', 'admin') DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Categories table
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    image_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Products table
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    category_id INT,
    image_url VARCHAR(255),
    gallery_images JSON,
    sizes JSON,
    colors JSON,
    stock_quantity INT DEFAULT 0,
    featured BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Cart items table
CREATE TABLE cart_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    size VARCHAR(10),
    color VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_cart_item (user_id, product_id, size, color)
);

-- Orders table
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    payment_status ENUM('pending', 'paid', 'failed') DEFAULT 'pending',
    payment_method VARCHAR(50),
    shipping_address JSON NOT NULL,
    estimated_delivery DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Order items table
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    size VARCHAR(10),
    color VARCHAR(50),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Insert sample categories
INSERT INTO categories (name, description, image_url) VALUES
('Traditional Kolos', 'Authentic Papua New Guinea traditional male garments', '/assets/images/kolo-category.jpg'),
('Meri Blouses', 'Beautiful traditional women\'s blouses', '/assets/images/meri-category.jpg'),
('Accessories', 'Traditional PNG accessories and ornaments', '/images/Accessories.jpg'),
('Ceremonial Wear', 'Special occasion and ceremonial clothing', '/assets/images/ceremonial-category.jpg');

-- Insert sample products
INSERT INTO products (name, description, price, category_id, image_url, gallery_images, sizes, colors, stock_quantity, featured) VALUES
('Classic Brown Kolo', 'Traditional handwoven kolo in rich brown tones, perfect for cultural celebrations', 89.99, 1, '/assets/images/brown-kolo.jpg', '["brown-kolo-1.jpg", "brown-kolo-2.jpg"]', '["S", "M", "L", "XL"]', '["Brown", "Dark Brown"]', 15, TRUE),
('Elegant Meri Blouse', 'Beautifully crafted traditional meri blouse with intricate patterns', 65.99, 2, '/assets/images/meri-blouse.jpg', '["meri-blouse-1.jpg", "meri-blouse-2.jpg"]', '["XS", "S", "M", "L", "XL"]', '["Red", "Blue", "Green"]', 20, TRUE),
('Traditional Headdress', 'Authentic PNG ceremonial headdress with feathers', 125.99, 3, '/assets/images/headdress.jpg', '["headdress-1.jpg"]', '["One Size"]', '["Natural"]', 8, FALSE),
('Ceremonial Kolo Set', 'Complete ceremonial outfit for special occasions', 199.99, 4, '/assets/images/ceremonial-set.jpg', '["ceremonial-1.jpg", "ceremonial-2.jpg"]', '["M", "L", "XL"]', '["Traditional", "Royal Blue"]', 5, TRUE);

-- Insert admin user (password: admin123)
INSERT INTO users (username, email, password_hash, full_name, role) VALUES
('admin', 'admin@pngheritage.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'PNG Heritage Admin', 'admin');

-- Insert sample customer (password: customer123)
INSERT INTO users (username, email, password_hash, full_name, role) VALUES
('customer', 'customer@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John Customer', 'customer');