<?php
// Start the session
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}

// Check if user is a therapist
if ($_SESSION['role'] !== 'therapist') {
    header("Location: ../index.php");
    exit();
}

// Include database connection
require_once '../db_connection.php';

// Error and success messages
$error = "";
$success = "";

// Handle form submission for adding new resource
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_resource'])) {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $category = $_POST['category'];
        $visibility = $_POST['visibility'];
        $patientIds = isset($_POST['patient_ids']) ? $_POST['patient_ids'] : [];
        
        // Validate inputs
        if (empty($title)) {
            $error = "Resource title is required.";
        } else {
            try {
                // Handle file upload
                $file = null;
                $fileName = null;
                $fileType = null;
                $filePath = null;
                
                if (isset($_FILES['resource_file']) && $_FILES['resource_file']['error'] == 0) {
                    $file = $_FILES['resource_file'];
                    $fileName = $file['name'];
                    $fileType = $file['type'];
                    $fileSize = $file['size'];
                    $fileTmpPath = $file['tmp_name'];
                    
                    // Validate file type
                    $allowedTypes = [
                        'application/pdf',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-powerpoint',
                        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                        'image/jpeg',
                        'image/png',
                        'image/gif',
                        'text/plain',
                        'application/zip',
                        'application/x-rar-compressed'
                    ];
                    
                    if (!in_array($fileType, $allowedTypes)) {
                        $error = "Invalid file type. Allowed types: PDF, Word, Excel, PowerPoint, Images, Text, ZIP, RAR.";
                    } else if ($fileSize > 15 * 1024 * 1024) { // 15MB limit
                        $error = "File size exceeds the limit of 15MB.";
                    } else {
                        // Create upload directory if it doesn't exist
                        $uploadDir = '../uploads/resources/';
                        if (!file_exists($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }
                        
                        // Generate unique filename
                        $fileExt = pathinfo($fileName, PATHINFO_EXTENSION);
                        $uniqueName = uniqid('resource_') . '.' . $fileExt;
                        $filePath = $uploadDir . $uniqueName;
                        
                        // Move the uploaded file
                        if (move_uploaded_file($fileTmpPath, $filePath)) {
                            $filePath = 'uploads/resources/' . $uniqueName; // Store relative path in DB
                        } else {
                            $error = "Error uploading file. Please try again.";
                        }
                    }
                } else if ($_FILES['resource_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $error = "Error uploading file: " . $_FILES['resource_file']['error'];
                }
                
                // If no error, add resource to database
                if (empty($error)) {
                    // Begin transaction
                    $conn->beginTransaction();
                    
                    // Insert resource
                    $stmt = $conn->prepare("INSERT INTO resources (title, description, category, file_name, file_type, file_path, created_by, visibility, created_at) 
                                          VALUES (:title, :description, :category, :file_name, :file_type, :file_path, :created_by, :visibility, NOW())");
                    
                    $stmt->bindParam(':title', $title);
                    $stmt->bindParam(':description', $description);
                    $stmt->bindParam(':category', $category);
                    $stmt->bindParam(':file_name', $fileName);
                    $stmt->bindParam(':file_type', $fileType);
                    $stmt->bindParam(':file_path', $filePath);
                    $stmt->bindParam(':created_by', $_SESSION['user_id']);
                    $stmt->bindParam(':visibility', $visibility);
                    $stmt->execute();
                    
                    $resourceId = $conn->lastInsertId();
                    
                    // If specific patients are selected, add resource-patient mappings
                    if ($visibility === 'specific' && !empty($patientIds)) {
                        $insertPatientStmt = $conn->prepare("INSERT INTO resource_patients (resource_id, patient_id) VALUES (:resource_id, :patient_id)");
                        
                        foreach ($patientIds as $patientId) {
                            $insertPatientStmt->bindParam(':resource_id', $resourceId);
                            $insertPatientStmt->bindParam(':patient_id', $patientId);
                            $insertPatientStmt->execute();
                        }
                    }
                    
                    // Commit transaction
                    $conn->commit();
                    
                    $success = "Resource added successfully!";
                }
                
            } catch(PDOException $e) {
                // Roll back transaction on error
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                $error = "Error adding resource: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['delete_resource'])) {
        $resourceId = $_POST['resource_id'];
        
        try {
            // Get file path before deletion
            $stmt = $conn->prepare("SELECT file_path FROM resources WHERE resource_id = :resource_id AND created_by = :user_id");
            $stmt->bindParam(':resource_id', $resourceId);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $filePath = $stmt->fetch()['file_path'];
                
                // Begin transaction
                $conn->beginTransaction();
                
                // Delete resource-patient mappings
                $stmt = $conn->prepare("DELETE FROM resource_patients WHERE resource_id = :resource_id");
                $stmt->bindParam(':resource_id', $resourceId);
                $stmt->execute();
                
                // Delete resource
                $stmt = $conn->prepare("DELETE FROM resources WHERE resource_id = :resource_id AND created_by = :user_id");
                $stmt->bindParam(':resource_id', $resourceId);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->execute();
                
                // Commit transaction
                $conn->commit();
                
                // Delete file if exists
                if ($filePath && file_exists('../' . $filePath)) {
                    unlink('../' . $filePath);
                }
                
                $success = "Resource deleted successfully!";
            } else {
                $error = "Resource not found or you don't have permission to delete it.";
            }
        } catch(PDOException $e) {
            // Roll back transaction on error
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $error = "Error deleting resource: " . $e->getMessage();
        }
    }
}

// Set up pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Get filter data
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$category = isset($_GET['category']) ? $_GET['category'] : '';
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

// Get total number of resources
try {
    // Base query parts
    $baseSelect = "SELECT COUNT(*) as total ";
    $baseFrom = "FROM resources ";
    $baseWhere = "WHERE created_by = :user_id ";
    $params = [':user_id' => $_SESSION['user_id']];
    
    // Apply category filter
    if (!empty($category)) {
        $baseWhere .= "AND category = :category ";
        $params[':category'] = $category;
    }
    
    // Apply search term
    if (!empty($searchTerm)) {
        $baseWhere .= "AND (title LIKE :search OR description LIKE :search) ";
        $params[':search'] = '%' . $searchTerm . '%';
    }
    
    // Apply visibility filter
    if ($filter !== 'all') {
        $baseWhere .= "AND visibility = :visibility ";
        $params[':visibility'] = $filter;
    }
    
    // Get total count for pagination
    $countQuery = $baseSelect . $baseFrom . $baseWhere;
    $stmt = $conn->prepare($countQuery);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $totalResources = $stmt->fetch()['total'];
    $totalPages = ceil($totalResources / $perPage);
    
} catch(PDOException $e) {
    $error = "Error retrieving resources count: " . $e->getMessage();
    $totalResources = 0;
    $totalPages = 0;
}

// Get resources with pagination
try {
    // Base query for data
    $baseSelect = "SELECT r.*, COUNT(rp.patient_id) as shared_count ";
    $baseFrom = "FROM resources r LEFT JOIN resource_patients rp ON r.resource_id = rp.resource_id ";
    
    // Add group by
    $baseWhere .= "GROUP BY r.resource_id ";
    
    // Add pagination
    $query = $baseSelect . $baseFrom . $baseWhere . "ORDER BY r.created_at DESC LIMIT :offset, :per_page";
    $stmt = $conn->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':per_page', $perPage, PDO::PARAM_INT);
    $stmt->execute();
    $resources = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $error = "Error retrieving resources: " . $e->getMessage();
    $resources = [];
}

// Get patients for sharing resources
try {
    $stmt = $conn->prepare("SELECT DISTINCT u.user_id, u.first_name, u.last_name 
                           FROM users u 
                           JOIN sessions s ON u.user_id = s.patient_id 
                           WHERE s.therapist_id = :therapist_id 
                           AND u.role = 'patient'
                           ORDER BY u.last_name, u.first_name");
    $stmt->bindParam(':therapist_id', $_SESSION['user_id']);
    $stmt->execute();
    $patients = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Error retrieving patients: " . $e->getMessage();
    $patients = [];
}

// Get resource categories
$resourceCategories = [
    'worksheets' => 'Worksheets & Exercises',
    'handouts' => 'Handouts & Information',
    'assessments' => 'Assessments & Questionnaires',
    'audio' => 'Audio Resources',
    'videos' => 'Video Resources',
    'research' => 'Research Articles',
    'books' => 'Book Recommendations',
    'apps' => 'App Recommendations',
    'websites' => 'Website Links',
    'other' => 'Other Resources'
];

// Get file type icon
function getFileTypeIcon($fileType) {
    if (strpos($fileType, 'pdf') !== false) {
        return '<i class="fas fa-file-pdf text-red-500"></i>';
    } else if (strpos($fileType, 'word') !== false || strpos($fileType, 'document') !== false) {
        return '<i class="fas fa-file-word text-blue-500"></i>';
    } else if (strpos($fileType, 'excel') !== false || strpos($fileType, 'spreadsheet') !== false) {
        return '<i class="fas fa-file-excel text-green-500"></i>';
    } else if (strpos($fileType, 'powerpoint') !== false || strpos($fileType, 'presentation') !== false) {
        return '<i class="fas fa-file-powerpoint text-orange-500"></i>';
    } else if (strpos($fileType, 'image') !== false) {
        return '<i class="fas fa-file-image text-purple-500"></i>';
    } else if (strpos($fileType, 'text') !== false) {
        return '<i class="fas fa-file-alt text-gray-500"></i>';
    } else if (strpos($fileType, 'zip') !== false || strpos($fileType, 'rar') !== false) {
        return '<i class="fas fa-file-archive text-yellow-500"></i>';
    } else {
        return '<i class="fas fa-file text-gray-500"></i>';
    }
}

// Format file size
function formatFileSize($bytes) {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } else if ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

// Build pagination URL
function buildPaginationUrl($page) {
    $params = $_GET;
    $params['page'] = $page;
    return '?' . http_build_query($params);
}

// Build filter URL
function buildFilterUrl($params = []) {
    $currentParams = $_GET;
    unset($currentParams['page']); // Reset pagination when filtering
    $mergedParams = array_merge($currentParams, $params);
    return '?' . http_build_query($mergedParams);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resources | Mental Space</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 font-sans">
    <!-- Include therapist sidebar and navigation -->
    <?php include 'therapist_nav.php'; ?>
    
    <div class="flex-1 p-8">
        <div class="max-w-7xl mx-auto">
            <?php if($error): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                    <p><?php echo $error; ?></p>
                </div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                    <p><?php echo $success; ?></p>
                </div>
            <?php endif; ?>
            
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-3xl font-bold text-gray-800">Therapeutic Resources</h1>
                <button id="addResourceBtn" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    <i class="fas fa-plus mr-2"></i> Add New Resource
                </button>
            </div>
            
            <!-- Filter Panel -->
            <div class="bg-white rounded-lg shadow p-4 mb-6">
                <form action="" method="GET" class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <!-- Search -->
                    <div class="sm:col-span-2">
                        <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <div class="mt-1 relative rounded-md shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-search text-gray-400"></i>
                            </div>
                            <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($searchTerm); ?>" class="focus:ring-green-500 focus:border-green-500 block w-full pl-10 sm:text-sm border-gray-300 rounded-md" placeholder="Search by title or description">
                        </div>
                    </div>
                    
                    <!-- Category Filter -->
                    <div>
                        <label for="category" class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                        <select id="category" name="category" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm rounded-md">
                            <option value="">All Categories</option>
                            <?php foreach ($resourceCategories as $key => $value): ?>
                                <option value="<?php echo $key; ?>" <?php echo $category == $key ? 'selected' : ''; ?>>
                                    <?php echo $value; ?>
                                </option>
                            <?php endforeach; ?>