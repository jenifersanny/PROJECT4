<?php
require_once 'config/database.php';

class PNGMarketplace {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // User authentication
    public function login($username, $password) {
        $query = "SELECT id, username, email, password_hash, full_name, role FROM users WHERE username = ? OR email = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$username, $username]);
        
        if ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];
                return $user;
            }
        }
        return false;
    }

    public function register($username, $email, $password, $full_name) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $query = "INSERT INTO users (username, email, password_hash, full_name) VALUES (?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        
        try {
            $stmt->execute([$username, $email, $password_hash, $full_name]);
            return $this->conn->lastInsertId();
        } catch (PDOException $e) {
            return false;
        }
    }

    // Categories
    public function getCategories() {
        $query = "SELECT * FROM categories ORDER BY name";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createCategory($name, $description, $image_url) {
        $query = "INSERT INTO categories (name, description, image_url) VALUES (?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$name, $description, $image_url]);
        return $this->conn->lastInsertId();
    }

    // Products
    public function getProducts($filters = []) {
        $where_conditions = [];
        $params = [];

        if (!empty($filters['category_id'])) {
            $where_conditions[] = "p.category_id = ?";
            $params[] = $filters['category_id'];
        }

        if (!empty($filters['featured'])) {
            $where_conditions[] = "p.featured = 1";
        }

        if (!empty($filters['search'])) {
            $where_conditions[] = "(p.name LIKE ? OR p.description LIKE ?)";
            $search_term = '%' . $filters['search'] . '%';
            $params[] = $search_term;
            $params[] = $search_term;
        }

        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        $query = "SELECT p.*, c.name as category_name 
                  FROM products p 
                  LEFT JOIN categories c ON p.category_id = c.id 
                  $where_clause 
                  ORDER BY p.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProduct($id) {
        $query = "SELECT p.*, c.name as category_name 
                  FROM products p 
                  LEFT JOIN categories c ON p.category_id = c.id 
                  WHERE p.id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createProduct($data) {
        $query = "INSERT INTO products (name, description, price, category_id, image_url, gallery_images, sizes, colors, stock_quantity, featured) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            $data['name'],
            $data['description'],
            $data['price'],
            $data['category_id'],
            $data['image_url'],
            json_encode($data['gallery_images'] ?? []),
            json_encode($data['sizes'] ?? []),
            json_encode($data['colors'] ?? []),
            $data['stock_quantity'] ?? 0,
            $data['featured'] ?? false
        ]);
        return $this->conn->lastInsertId();
    }

    // Cart operations
    public function getCartItems($user_id) {
        $query = "SELECT ci.*, p.name, p.price, p.image_url 
                  FROM cart_items ci 
                  JOIN products p ON ci.product_id = p.id 
                  WHERE ci.user_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addToCart($user_id, $product_id, $quantity, $size = null, $color = null) {
        $query = "INSERT INTO cart_items (user_id, product_id, quantity, size, color) 
                  VALUES (?, ?, ?, ?, ?) 
                  ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$user_id, $product_id, $quantity, $size, $color]);
    }

    public function updateCartItem($cart_item_id, $quantity) {
        $query = "UPDATE cart_items SET quantity = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$quantity, $cart_item_id]);
    }

    public function removeFromCart($cart_item_id) {
        $query = "DELETE FROM cart_items WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$cart_item_id]);
    }

    public function clearCart($user_id) {
        $query = "DELETE FROM cart_items WHERE user_id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$user_id]);
    }

    // Orders
    public function createOrder($user_id, $total_amount, $shipping_address, $payment_method, $cart_items) {
        $this->conn->beginTransaction();
        
        try {
            // Create order
            $query = "INSERT INTO orders (user_id, total_amount, shipping_address, payment_method, estimated_delivery) 
                      VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$user_id, $total_amount, json_encode($shipping_address), $payment_method]);
            $order_id = $this->conn->lastInsertId();

            // Add order items
            $query = "INSERT INTO order_items (order_id, product_id, quantity, price, size, color) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($query);
            
            foreach ($cart_items as $item) {
                $stmt->execute([
                    $order_id,
                    $item['product_id'],
                    $item['quantity'],
                    $item['price'],
                    $item['size'],
                    $item['color']
                ]);
            }

            // Clear cart
            $this->clearCart($user_id);
            
            $this->conn->commit();
            return $order_id;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    public function getUserOrders($user_id) {
        $query = "SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOrder($order_id) {
        $query = "SELECT o.*, oi.*, p.name as product_name, p.image_url 
                  FROM orders o 
                  LEFT JOIN order_items oi ON o.id = oi.order_id 
                  LEFT JOIN products p ON oi.product_id = p.id 
                  WHERE o.id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$order_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>