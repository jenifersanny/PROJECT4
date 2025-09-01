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

if (!isLoggedIn()) {
    sendJsonResponse(['message' => 'Authentication required'], 401);
}

$marketplace = new PNGMarketplace();
$method = $_SERVER['REQUEST_METHOD'];
$request = json_decode(file_get_contents('php://input'), true);
$user_id = getCurrentUserId();

switch ($method) {
    case 'GET':
        handleGetCart($marketplace, $user_id);
        break;
        
    case 'POST':
        handleAddToCart($marketplace, $user_id, $request);
        break;
        
    case 'PUT':
        if (isset($_GET['id'])) {
            handleUpdateCartItem($marketplace, $_GET['id'], $request);
        } else {
            sendJsonResponse(['message' => 'Cart item ID required'], 400);
        }
        break;
        
    case 'DELETE':
        if (isset($_GET['id'])) {
            handleRemoveFromCart($marketplace, $_GET['id']);
        } else if (isset($_GET['action']) && $_GET['action'] === 'clear') {
            handleClearCart($marketplace, $user_id);
        } else {
            sendJsonResponse(['message' => 'Cart item ID required'], 400);
        }
        break;
        
    default:
        sendJsonResponse(['message' => 'Method not allowed'], 405);
}

function handleGetCart($marketplace, $user_id) {
    $cart_items = $marketplace->getCartItems($user_id);
    
    $total = 0;
    foreach ($cart_items as $item) {
        $total += $item['price'] * $item['quantity'];
    }
    
    sendJsonResponse([
        'items' => $cart_items,
        'total' => number_format($total, 2),
        'count' => count($cart_items)
    ]);
}

function handleAddToCart($marketplace, $user_id, $request) {
    $required_fields = ['product_id', 'quantity'];
    
    foreach ($required_fields as $field) {
        if (!isset($request[$field])) {
            sendJsonResponse(['message' => "Field $field is required"], 400);
        }
    }
    
    if (!is_numeric($request['quantity']) || $request['quantity'] <= 0) {
        sendJsonResponse(['message' => 'Invalid quantity'], 400);
    }
    
    $product_id = intval($request['product_id']);
    $quantity = intval($request['quantity']);
    $size = $request['size'] ?? null;
    $color = $request['color'] ?? null;
    
    // Check if product exists
    $product = $marketplace->getProduct($product_id);
    if (!$product) {
        sendJsonResponse(['message' => 'Product not found'], 404);
    }
    
    $success = $marketplace->addToCart($user_id, $product_id, $quantity, $size, $color);
    
    if ($success) {
        $cart_items = $marketplace->getCartItems($user_id);
        sendJsonResponse([
            'success' => true,
            'message' => 'Item added to cart',
            'cart' => $cart_items
        ]);
    } else {
        sendJsonResponse(['message' => 'Failed to add item to cart'], 500);
    }
}

function handleUpdateCartItem($marketplace, $cart_item_id, $request) {
    if (!isset($request['quantity'])) {
        sendJsonResponse(['message' => 'Quantity is required'], 400);
    }
    
    if (!is_numeric($request['quantity']) || $request['quantity'] <= 0) {
        sendJsonResponse(['message' => 'Invalid quantity'], 400);
    }
    
    $quantity = intval($request['quantity']);
    $success = $marketplace->updateCartItem(intval($cart_item_id), $quantity);
    
    if ($success) {
        sendJsonResponse(['success' => true, 'message' => 'Cart item updated']);
    } else {
        sendJsonResponse(['message' => 'Failed to update cart item'], 500);
    }
}

function handleRemoveFromCart($marketplace, $cart_item_id) {
    $success = $marketplace->removeFromCart(intval($cart_item_id));
    
    if ($success) {
        sendJsonResponse(['success' => true, 'message' => 'Item removed from cart']);
    } else {
        sendJsonResponse(['message' => 'Failed to remove item from cart'], 500);
    }
}

function handleClearCart($marketplace, $user_id) {
    $success = $marketplace->clearCart($user_id);
    
    if ($success) {
        sendJsonResponse(['success' => true, 'message' => 'Cart cleared']);
    } else {
        sendJsonResponse(['message' => 'Failed to clear cart'], 500);
    }
}
?>