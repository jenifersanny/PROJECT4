<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$marketplace = new PNGMarketplace();
$method = $_SERVER['REQUEST_METHOD'];
$request = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            handleGetProduct($marketplace, $_GET['id']);
        } else {
            handleGetProducts($marketplace, $_GET);
        }
        break;
        
    case 'POST':
        if (!isLoggedIn() || !isAdmin()) {
            sendJsonResponse(['message' => 'Unauthorized'], 403);
        }
        handleCreateProduct($marketplace, $request);
        break;
        
    case 'PUT':
        if (!isLoggedIn() || !isAdmin()) {
            sendJsonResponse(['message' => 'Unauthorized'], 403);
        }
        if (isset($_GET['id'])) {
            handleUpdateProduct($marketplace, $_GET['id'], $request);
        } else {
            sendJsonResponse(['message' => 'Product ID required'], 400);
        }
        break;
        
    case 'DELETE':
        if (!isLoggedIn() || !isAdmin()) {
            sendJsonResponse(['message' => 'Unauthorized'], 403);
        }
        if (isset($_GET['id'])) {
            handleDeleteProduct($marketplace, $_GET['id']);
        } else {
            sendJsonResponse(['message' => 'Product ID required'], 400);
        }
        break;
        
    default:
        sendJsonResponse(['message' => 'Method not allowed'], 405);
}

function handleGetProducts($marketplace, $filters) {
    $filter_array = [];
    
    if (isset($filters['category_id'])) {
        $filter_array['category_id'] = intval($filters['category_id']);
    }
    
    if (isset($filters['featured']) && $filters['featured'] === 'true') {
        $filter_array['featured'] = true;
    }
    
    if (isset($filters['search']) && !empty($filters['search'])) {
        $filter_array['search'] = $filters['search'];
    }
    
    $products = $marketplace->getProducts($filter_array);
    
    // Parse JSON fields
    foreach ($products as &$product) {
        if ($product['gallery_images']) {
            $product['gallery_images'] = json_decode($product['gallery_images'], true);
        }
        if ($product['sizes']) {
            $product['sizes'] = json_decode($product['sizes'], true);
        }
        if ($product['colors']) {
            $product['colors'] = json_decode($product['colors'], true);
        }
    }
    
    sendJsonResponse($products);
}

function handleGetProduct($marketplace, $id) {
    $product = $marketplace->getProduct(intval($id));
    
    if (!$product) {
        sendJsonResponse(['message' => 'Product not found'], 404);
    }
    
    // Parse JSON fields
    if ($product['gallery_images']) {
        $product['gallery_images'] = json_decode($product['gallery_images'], true);
    }
    if ($product['sizes']) {
        $product['sizes'] = json_decode($product['sizes'], true);
    }
    if ($product['colors']) {
        $product['colors'] = json_decode($product['colors'], true);
    }
    
    sendJsonResponse($product);
}

function handleCreateProduct($marketplace, $request) {
    $required_fields = ['name', 'description', 'price', 'category_id'];
    
    foreach ($required_fields as $field) {
        if (!isset($request[$field]) || empty($request[$field])) {
            sendJsonResponse(['message' => "Field $field is required"], 400);
        }
    }
    
    if (!is_numeric($request['price']) || $request['price'] <= 0) {
        sendJsonResponse(['message' => 'Invalid price'], 400);
    }
    
    $product_data = [
        'name' => $request['name'],
        'description' => $request['description'],
        'price' => floatval($request['price']),
        'category_id' => intval($request['category_id']),
        'image_url' => $request['image_url'] ?? '',
        'gallery_images' => $request['gallery_images'] ?? [],
        'sizes' => $request['sizes'] ?? [],
        'colors' => $request['colors'] ?? [],
        'stock_quantity' => intval($request['stock_quantity'] ?? 0),
        'featured' => $request['featured'] ?? false
    ];
    
    $product_id = $marketplace->createProduct($product_data);
    
    if ($product_id) {
        $product = $marketplace->getProduct($product_id);
        sendJsonResponse($product);
    } else {
        sendJsonResponse(['message' => 'Failed to create product'], 500);
    }
}

function handleUpdateProduct($marketplace, $id, $request) {
    $product_id = intval($id);
    $existing_product = $marketplace->getProduct($product_id);
    
    if (!$existing_product) {
        sendJsonResponse(['message' => 'Product not found'], 404);
    }
    
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        $update_data = [];
        $params = [];
        
        if (isset($request['name'])) {
            $update_data[] = "name = ?";
            $params[] = $request['name'];
        }
        if (isset($request['description'])) {
            $update_data[] = "description = ?";
            $params[] = $request['description'];
        }
        if (isset($request['price'])) {
            if (!is_numeric($request['price']) || $request['price'] <= 0) {
                sendJsonResponse(['message' => 'Invalid price'], 400);
            }
            $update_data[] = "price = ?";
            $params[] = floatval($request['price']);
        }
        if (isset($request['category_id'])) {
            $update_data[] = "category_id = ?";
            $params[] = intval($request['category_id']);
        }
        if (isset($request['image_url'])) {
            $update_data[] = "image_url = ?";
            $params[] = $request['image_url'];
        }
        if (isset($request['gallery_images'])) {
            $update_data[] = "gallery_images = ?";
            $params[] = json_encode($request['gallery_images']);
        }
        if (isset($request['sizes'])) {
            $update_data[] = "sizes = ?";
            $params[] = json_encode($request['sizes']);
        }
        if (isset($request['colors'])) {
            $update_data[] = "colors = ?";
            $params[] = json_encode($request['colors']);
        }
        if (isset($request['stock_quantity'])) {
            $update_data[] = "stock_quantity = ?";
            $params[] = intval($request['stock_quantity']);
        }
        if (isset($request['featured'])) {
            $update_data[] = "featured = ?";
            $params[] = $request['featured'] ? 1 : 0;
        }
        
        $params[] = $product_id;
        
        $sql = "UPDATE products SET " . implode(', ', $update_data) . ", updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
        $updated_product = $marketplace->getProduct($product_id);
        sendJsonResponse($updated_product);
    } catch (Exception $e) {
        sendJsonResponse(['message' => 'Failed to update product'], 500);
    }
}

function handleDeleteProduct($marketplace, $id) {
    $product_id = intval($id);
    $existing_product = $marketplace->getProduct($product_id);
    
    if (!$existing_product) {
        sendJsonResponse(['message' => 'Product not found'], 404);
    }
    
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        $sql = "DELETE FROM products WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$product_id]);
        
        sendJsonResponse(['message' => 'Product deleted successfully']);
    } catch (Exception $e) {
        sendJsonResponse(['message' => 'Failed to delete product'], 500);
    }
}
?>