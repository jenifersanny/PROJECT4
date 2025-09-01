<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$marketplace = new PNGMarketplace();
$method = $_SERVER['REQUEST_METHOD'];
$request = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        handleGetCategories($marketplace);
        break;
        
    case 'POST':
        if (!isLoggedIn() || !isAdmin()) {
            sendJsonResponse(['message' => 'Unauthorized'], 403);
        }
        handleCreateCategory($marketplace, $request);
        break;
        
    default:
        sendJsonResponse(['message' => 'Method not allowed'], 405);
}

function handleGetCategories($marketplace) {
    $categories = $marketplace->getCategories();
    sendJsonResponse($categories);
}

function handleCreateCategory($marketplace, $request) {
    $required_fields = ['name', 'description'];
    
    foreach ($required_fields as $field) {
        if (!isset($request[$field]) || empty($request[$field])) {
            sendJsonResponse(['message' => "Field $field is required"], 400);
        }
    }
    
    $category_id = $marketplace->createCategory(
        $request['name'],
        $request['description'],
        $request['image_url'] ?? ''
    );
    
    if ($category_id) {
        sendJsonResponse([
            'success' => true,
            'message' => 'Category created successfully',
            'id' => $category_id
        ]);
    } else {
        sendJsonResponse(['message' => 'Failed to create category'], 500);
    }
}
?>