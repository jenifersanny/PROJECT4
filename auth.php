<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$marketplace = new PNGMarketplace();
$method = $_SERVER['REQUEST_METHOD'];
$request = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'POST':
        if (isset($_GET['action'])) {
            switch ($_GET['action']) {
                case 'login':
                    handleLogin($marketplace, $request);
                    break;
                case 'register':
                    handleRegister($marketplace, $request);
                    break;
                case 'logout':
                    handleLogout();
                    break;
                default:
                    sendJsonResponse(['message' => 'Invalid action'], 400);
            }
        } else {
            sendJsonResponse(['message' => 'Action required'], 400);
        }
        break;
        
    case 'GET':
        if (isset($_GET['action']) && $_GET['action'] === 'user') {
            handleGetUser();
        } else {
            sendJsonResponse(['message' => 'Invalid action'], 400);
        }
        break;
        
    default:
        sendJsonResponse(['message' => 'Method not allowed'], 405);
}

function handleLogin($marketplace, $request) {
    if (!isset($request['username']) || !isset($request['password'])) {
        sendJsonResponse(['message' => 'Username and password required'], 400);
    }
    
    $user = $marketplace->login($request['username'], $request['password']);
    
    if ($user) {
        sendJsonResponse([
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'full_name' => $user['full_name'],
                'role' => $user['role']
            ]
        ]);
    } else {
        sendJsonResponse(['message' => 'Invalid credentials'], 401);
    }
}

function handleRegister($marketplace, $request) {
    $required_fields = ['username', 'email', 'password', 'full_name'];
    
    foreach ($required_fields as $field) {
        if (!isset($request[$field]) || empty($request[$field])) {
            sendJsonResponse(['message' => "Field $field is required"], 400);
        }
    }
    
    if (strlen($request['password']) < 6) {
        sendJsonResponse(['message' => 'Password must be at least 6 characters'], 400);
    }
    
    if (!filter_var($request['email'], FILTER_VALIDATE_EMAIL)) {
        sendJsonResponse(['message' => 'Invalid email format'], 400);
    }
    
    $user_id = $marketplace->register(
        $request['username'],
        $request['email'],
        $request['password'],
        $request['full_name']
    );
    
    if ($user_id) {
        // Auto login after registration
        $user = $marketplace->login($request['username'], $request['password']);
        sendJsonResponse([
            'success' => true,
            'message' => 'Registration successful',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'full_name' => $user['full_name'],
                'role' => $user['role']
            ]
        ]);
    } else {
        sendJsonResponse(['message' => 'Registration failed. Username or email may already exist.'], 400);
    }
}

function handleLogout() {
    session_destroy();
    sendJsonResponse(['success' => true, 'message' => 'Logged out successfully']);
}

function handleGetUser() {
    if (isLoggedIn()) {
        sendJsonResponse([
            'success' => true,
            'user' => [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'full_name' => $_SESSION['full_name'],
                'role' => $_SESSION['user_role']
            ]
        ]);
    } else {
        sendJsonResponse(['message' => 'Not authenticated'], 401);
    }
}
?>